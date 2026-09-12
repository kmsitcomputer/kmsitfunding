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
        DB::transaction(function () use ($user, $newPassword) {
            $user->forceFill(['password' => Hash::make($newPassword)])->save();
            Password::deleteToken($user);
        });

        $this->applyPostChangeInvalidation($request, $user);

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
                DB::transaction(function () use ($user, $password) {
                    $user->forceFill(['password' => Hash::make($password)])->save();
                });

                $this->applyPostChangeInvalidation($request, $user);

                $this->audit->record('password_reset_completed', $user);
            }
        );

        return $status === Password::PASSWORD_RESET;
    }

    private function applyPostChangeInvalidation(Request $request, User $user): void
    {
        $this->sessions->invalidateAllExcept($user, $request->session()->getId());
        $request->session()->regenerate();
        $this->assurance->invalidate();
    }
}
