<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

/**
 * Email + password authentication (Q21 baseline), with an optional TOTP second
 * factor (Q23). See "Authentication Flows > Login" in
 * docs/implementation/IMP-002-identity-authentication.md.
 *
 * No business-role redirect logic belongs here — login establishes only an
 * authenticated Identity + STANDARD assurance, never a role/business authority.
 */
class AuthenticationService
{
    private const PENDING_MFA_SESSION_KEY = 'identity.pending_mfa_user_id';

    public function __construct(
        private readonly EmailNormalizer $normalizer,
        private readonly MfaService $mfa,
        private readonly AssuranceService $assurance,
        private readonly IdentityAuditLogger $audit,
    ) {}

    /**
     * @return array{status: 'authenticated'|'mfa_required'|'failed'}
     */
    public function attemptPassword(Request $request, string $email, string $password): array
    {
        $normalized = $this->normalizer->normalize($email);
        $user = User::where('email', $normalized)->first();

        // Constant-time-safe: always run a hash comparison, even when no user
        // exists, so response timing does not reveal account existence.
        $passwordMatches = Hash::check($password, $user?->password ?? Hash::make(''));

        if ($user === null || ! $passwordMatches) {
            $this->audit->record('login_failed', $user, ['reason' => 'invalid_credentials']);

            return ['status' => 'failed'];
        }

        if (! $user->canAuthenticate()) {
            $this->audit->record('login_failed', $user, ['reason' => 'security_restriction']);

            return ['status' => 'failed'];
        }

        if ($user->mfa_enabled) {
            Session::put(self::PENDING_MFA_SESSION_KEY, $user->id);

            return ['status' => 'mfa_required'];
        }

        $this->finalizeLogin($request, $user);

        return ['status' => 'authenticated'];
    }

    /**
     * @return array{status: 'authenticated'|'failed'|'no_pending_challenge'}
     */
    public function attemptMfaChallenge(Request $request, string $code, bool $isRecoveryCode = false): array
    {
        $userId = Session::get(self::PENDING_MFA_SESSION_KEY);

        if ($userId === null) {
            return ['status' => 'no_pending_challenge'];
        }

        $user = User::find($userId);

        if ($user === null || ! $user->canAuthenticate()) {
            Session::forget(self::PENDING_MFA_SESSION_KEY);

            return ['status' => 'failed'];
        }

        $accepted = $isRecoveryCode
            ? $this->mfa->consumeRecoveryCode($user, $code)
            : $this->mfa->verifyChallenge($user, 'login', $code);

        if (! $accepted) {
            $this->audit->record('login_failed', $user, ['reason' => 'mfa_challenge_failed']);

            return ['status' => 'failed'];
        }

        Session::forget(self::PENDING_MFA_SESSION_KEY);
        $this->finalizeLogin($request, $user);

        return ['status' => 'authenticated'];
    }

    public function logout(Request $request): void
    {
        $user = Auth::user();

        Auth::logout();
        $this->assurance->invalidate();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user instanceof User) {
            $this->audit->record('logout', $user);
        }
    }

    private function finalizeLogin(Request $request, User $user): void
    {
        Auth::login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->record('login_succeeded', $user);
    }
}
