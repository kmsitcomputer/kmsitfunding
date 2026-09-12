<?php

namespace App\Services\Identity;

use App\Models\MfaSecret;
use App\Models\User;
use Illuminate\Http\Request;
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
 * Replay protection (M04, mandatory) covers all four required contexts —
 * enrollment_confirm, elevate, disable, reset — via `MfaSecret.accepted_steps`,
 * which tracks the last accepted TOTP time-step per (User, context) pair using
 * google2fa's `verifyKeyNewer()`. Consumption is guarded by a row lock so
 * concurrent submissions of the same code resolve to exactly one success.
 */
class MfaService
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly IdentityAuditLogger $audit,
        private readonly AssuranceService $assurance,
        private readonly SessionInvalidator $sessions,
    ) {}

    public function startEnrollment(User $user): array
    {
        // IMP002-IMPL-M05: starting (re-)enrollment changes the identity's MFA
        // security configuration. A previously-earned ELEVATED assurance is no
        // longer trusted from this point, regardless of whether the new
        // enrollment is ever confirmed — invalidate it immediately, not only
        // once confirmation completes.
        $this->assurance->invalidate();

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
     * Self-service disable. IMP002-IMPL-M04: requires the identity to prove
     * current possession of the active factor (a fresh, replay-guarded TOTP
     * code for context 'disable', or a recovery code) — this IS the "disable
     * confirmation" the mandatory replay-protection contract requires; a
     * missing/invalid proof fails without disabling anything. Fresh password
     * and ELEVATED assurance are enforced by the caller (MfaController) before
     * this is ever reached.
     *
     * @return bool whether the proof succeeded and MFA was disabled
     */
    public function disable(Request $request, User $user, ?string $code, ?string $recoveryCode): bool
    {
        return $this->confirmAndInvalidate($request, $user, 'disable', $code, $recoveryCode);
    }

    /**
     * Self-service reset — functionally identical consequence to disable()
     * (old secret and recovery codes invalidated, sessions/assurance reset),
     * but audited/keyed under its own replay context ('reset') so an identity
     * that still holds its current device can rotate its factor without an
     * administrator, then immediately re-enroll via startEnrollment().
     * Administrative (non-self-service) reset authority remains an IMP-003+
     * concern — this is not that.
     *
     * @return bool whether the proof succeeded and MFA was reset
     */
    public function reset(Request $request, User $user, ?string $code, ?string $recoveryCode): bool
    {
        return $this->confirmAndInvalidate($request, $user, 'reset', $code, $recoveryCode);
    }

    private function confirmAndInvalidate(Request $request, User $user, string $context, ?string $code, ?string $recoveryCode): bool
    {
        $confirmed = match (true) {
            $code !== null && $code !== '' => $this->verifyChallenge($user, $context, $code),
            $recoveryCode !== null && $recoveryCode !== '' => $this->consumeRecoveryCode($user, $recoveryCode),
            default => false,
        };

        if (! $confirmed) {
            return false;
        }

        // IMP002-IMPL-M07: the credential/security mutation (secret + recovery
        // codes gone, mfa_enabled false) and the other-session invalidation are
        // both plain DB writes — committed as ONE transaction so a failure
        // partway through cannot leave MFA disabled/reset while an old session
        // is still valid, or vice versa. Current-session ID rotation is a
        // framework session-store operation, not a DB row this connection's
        // transaction can enforce atomically (see PasswordService for the
        // same reasoning) — it is performed immediately after commit.
        DB::transaction(function () use ($request, $user) {
            MfaSecret::where('user_id', $user->id)->delete();
            $user->forceFill(['mfa_enabled' => false])->save();
            $this->sessions->invalidateAllExcept($user, $request->session()->getId());
        });

        // IMP002-REAUDIT (M07 follow-up): same fail-closed ordering as
        // PasswordService — invalidate ELEVATED before the fallible
        // regenerate() call, so a session-rotation failure can never leave a
        // retained session still carrying pre-transition ELEVATED assurance.
        $this->assurance->invalidate();
        $request->session()->regenerate();

        $this->audit->record('mfa_reset_or_disabled', $user);

        return true;
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
