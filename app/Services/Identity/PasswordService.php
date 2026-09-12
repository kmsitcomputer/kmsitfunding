<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Password credential handling: ordinary authenticated change, and the
 * framework password-broker-based reset flow (see "Credential Model",
 * "Password Reset", and "Credential Model > Password Change — Session
 * Consequences" in docs/implementation/IMP-002-identity-authentication.md).
 *
 * Both paths apply the SAME session/assurance invalidation consequences —
 * ordinary change and recovery-flow reset are equally sensitive.
 */
class PasswordService
{
    public function __construct(
        private readonly SessionInvalidator $sessions,
        private readonly AssuranceService $assurance,
        private readonly IdentityAuditLogger $audit,
    ) {}

    public function changePassword(Request $request, User $user, string $newPassword): void
    {
        // IMP002-IMPL-M07: the credential mutation and the other-session
        // invalidation are both plain DB writes on this connection — commit
        // them as ONE transaction so a failure partway through can never
        // leave the new password committed while a stale session remains
        // valid, or vice versa. Current-session ID regeneration and
        // ELEVATED-assurance invalidation are framework session-store
        // operations, not DB rows this transaction can enforce atomically —
        // they run immediately after commit (see "Security Transition
        // Atomicity" in docs/audits/IMP-002-IMPLEMENTATION-REMEDIATION-1.md).
        DB::transaction(function () use ($request, $user, $newPassword) {
            $user->forceFill(['password' => Hash::make($newPassword)])->save();
            Password::deleteToken($user);
            $this->sessions->invalidateAllExcept($user, $request->session()->getId());
        });

        $request->session()->regenerate();
        $this->assurance->invalidate();

        $this->audit->record('password_changed', $user);
    }

    public function sendResetLink(string $email): void
    {
        // Laravel's framework PasswordBroker already returns a status regardless of
        // whether the account exists; we deliberately do not branch on it here so
        // the caller can return the same generic response either way (see "Privacy").
        Password::sendResetLink(['email' => $email]);
    }

    /**
     * @return bool whether the reset succeeded
     */
    public function completeReset(Request $request, string $email, string $token, string $newPassword): bool
    {
        $status = Password::reset(
            ['email' => $email, 'password' => $newPassword, 'password_confirmation' => $newPassword, 'token' => $token],
            function (User $user, string $password) use ($request) {
                // Same reasoning as changePassword(): the credential mutation
                // and other-session invalidation are one transaction; current-
                // session rotation and assurance invalidation follow after commit.
                DB::transaction(function () use ($request, $user, $password) {
                    $user->forceFill(['password' => Hash::make($password)])->save();
                    $this->sessions->invalidateAllExcept($user, $request->session()->getId());
                });

                $request->session()->regenerate();
                $this->assurance->invalidate();

                $this->audit->record('password_reset_completed', $user);
            }
        );

        return $status === Password::PASSWORD_RESET;
    }
}
