<?php

namespace App\Services\Identity;

use App\Models\MfaSecret;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Q23 / M04 — Configurable MFA, TOTP baseline, via the maintained pragmarx/google2fa
 * library (RFC 6238; see "MFA > Implementation Contract" and "Dependency Policy" in
 * docs/implementation/IMP-002-identity-authentication.md for the selection rationale
 * — no custom TOTP cryptography).
 *
 * Replay protection (M04, mandatory): `MfaSecret.accepted_steps` tracks, per
 * sensitive context (enrollment_confirm / elevate / disable / reset), the last
 * TOTP timestamp accepted for that context, using google2fa's `verifyKeyNewer()`
 * — the same accepted code can never be reused within its own time step for the
 * same (User, context) pair. Consumption is guarded by a row lock so concurrent
 * submissions of the same code resolve to exactly one success.
 */
class MfaService
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly IdentityAuditLogger $audit,
    ) {}

    public function startEnrollment(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $mfa = MfaSecret::firstOrNew(['user_id' => $user->id]);
        $mfa->user_id = $user->id;
        $mfa->pending_secret = $secret;
        $mfa->pending_secret_expires_at = now()->addMinutes(
            (int) config('identity.mfa_pending_enrollment_ttl_minutes')
        );
        $mfa->save();

        // Standard otpauth:// URI construction (RFC-adjacent, "Key Uri Format" as used
        // by Google Authenticator and compatible apps) — this is URI assembly, not a
        // cryptographic operation; the TOTP algorithm itself is entirely delegated to
        // the pragmarx/google2fa library above.
        $issuer = rawurlencode((string) config('app.name'));
        $label = rawurlencode((string) config('app.name')).':'.rawurlencode($user->email);
        $otpauthUri = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}";

        return ['secret' => $secret, 'otpauth_uri' => $otpauthUri];
    }

    /**
     * @return array{success: bool, recovery_codes?: array<string>}
     */
    public function confirmEnrollment(User $user, string $code): array
    {
        return DB::transaction(function () use ($user, $code) {
            /** @var MfaSecret|null $mfa */
            $mfa = MfaSecret::where('user_id', $user->id)->lockForUpdate()->first();

            if ($mfa === null || ! $mfa->hasPendingEnrollment()) {
                return ['success' => false];
            }

            $accepted = $this->verifyWithReplayGuard($mfa, 'enrollment_confirm', $mfa->pending_secret, $code);

            if ($accepted === null) {
                return ['success' => false];
            }

            $recoveryCodes = $this->generateRecoveryCodes();

            $mfa->secret = $mfa->pending_secret;
            $mfa->pending_secret = null;
            $mfa->pending_secret_expires_at = null;
            $mfa->recovery_codes = array_map(
                fn (string $plain) => ['hash' => Hash::make($plain), 'used_at' => null],
                $recoveryCodes,
            );
            $mfa->save();

            $user->forceFill(['mfa_enabled' => true])->save();

            $this->audit->record('mfa_enrolled', $user);

            return ['success' => true, 'recovery_codes' => $recoveryCodes];
        });
    }

    /**
     * Verify a TOTP challenge for an already-enrolled identity, for the given
     * sensitive context ('login', 'elevate', 'disable', or 'reset').
     */
    public function verifyChallenge(User $user, string $context, string $code): bool
    {
        return DB::transaction(function () use ($user, $context, $code) {
            /** @var MfaSecret|null $mfa */
            $mfa = MfaSecret::where('user_id', $user->id)->lockForUpdate()->first();

            if ($mfa === null || $mfa->secret === null) {
                return false;
            }

            $accepted = $this->verifyWithReplayGuard($mfa, $context, $mfa->secret, $code);

            return $accepted !== null;
        });
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            /** @var MfaSecret|null $mfa */
            $mfa = MfaSecret::where('user_id', $user->id)->lockForUpdate()->first();

            if ($mfa === null || empty($mfa->recovery_codes)) {
                return false;
            }

            $codes = $mfa->recovery_codes;

            foreach ($codes as $index => $entry) {
                if ($entry['used_at'] === null && Hash::check($code, $entry['hash'])) {
                    $codes[$index]['used_at'] = now()->toISOString();
                    $mfa->recovery_codes = $codes;
                    $mfa->save();

                    $this->audit->record('mfa_recovery_code_used', $user);

                    return true;
                }
            }

            return false;
        });
    }

    public function regenerateRecoveryCodes(User $user): array
    {
        return DB::transaction(function () use ($user) {
            /** @var MfaSecret $mfa */
            $mfa = MfaSecret::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            $recoveryCodes = $this->generateRecoveryCodes();

            $mfa->recovery_codes = array_map(
                fn (string $plain) => ['hash' => Hash::make($plain), 'used_at' => null],
                $recoveryCodes,
            );
            $mfa->save();

            $this->audit->record('mfa_recovery_codes_regenerated', $user);

            return $recoveryCodes;
        });
    }

    /**
     * Reset/disable MFA. Deterministic consequences (M04): TOTP secret and
     * recovery codes invalidated. Session/assurance invalidation is the
     * caller's responsibility (see MfaController / PasswordConfirmation
     * middleware), since that spans concerns beyond this service.
     */
    public function disable(User $user): void
    {
        DB::transaction(function () use ($user) {
            MfaSecret::where('user_id', $user->id)->delete();
            $user->forceFill(['mfa_enabled' => false])->save();
        });

        $this->audit->record('mfa_reset_or_disabled', $user);
    }

    private function verifyWithReplayGuard(MfaSecret $mfa, string $context, string $secret, string $code): ?int
    {
        // 0 (never a real TOTP time-step) rather than null: verifyKeyNewer()
        // only returns the matched time-step as an int when $oldTimestamp is
        // non-null — with null it returns bare `true`, which would then be
        // stored as the "last accepted" marker and defeat replay protection
        // on the very next call.
        $lastAccepted = $mfa->accepted_steps[$context] ?? 0;

        $accepted = $this->google2fa->verifyKeyNewer($secret, $code, $lastAccepted);

        if ($accepted === false) {
            return null;
        }

        $steps = $mfa->accepted_steps ?? [];
        $steps[$context] = $accepted;
        $mfa->accepted_steps = $steps;
        $mfa->save();

        return $accepted;
    }

    /**
     * @return array<string>
     */
    private function generateRecoveryCodes(): array
    {
        $count = (int) config('identity.mfa_recovery_code_count');

        return array_map(
            fn () => Str::upper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4)),
            range(1, $count),
        );
    }
}
