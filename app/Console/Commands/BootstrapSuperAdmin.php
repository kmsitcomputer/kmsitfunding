<?php

namespace App\Console\Commands;

use App\Models\SuperAdminBootstrap;
use App\Models\User;
use App\Services\Identity\EmailNormalizer;
use App\Services\Identity\IdentityAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Q25 — controlled, one-time CLI bootstrap of the first Super Admin identity.
 *
 * No public web endpoint exists for this. No default email/password. The
 * operator supplies both interactively; neither is ever echoed back or
 * logged in plaintext. IMP-002 creates only the Identity — canonical Super
 * Admin role/permission/scope/business authority remains owned by IMP-003;
 * this command does not create any role/permission table or record.
 */
class BootstrapSuperAdmin extends Command
{
    protected $signature = 'identity:bootstrap-super-admin';

    protected $description = 'One-time controlled bootstrap of the first Super Admin identity (Q25).';

    public function handle(EmailNormalizer $normalizer, IdentityAuditLogger $audit): int
    {
        if (SuperAdminBootstrap::where('lock_key', SuperAdminBootstrap::LOCK_KEY)->exists()) {
            $this->error('A Super Admin has already been bootstrapped. This command may only run once.');

            return self::FAILURE;
        }

        $email = $normalizer->normalize((string) $this->ask('Super Admin email'));
        $password = (string) $this->secret('Super Admin password');
        $confirmPassword = (string) $this->secret('Confirm password');

        try {
            $this->validateInput($email, $password, $confirmPassword);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        try {
            $user = DB::transaction(function () use ($email, $password) {
                // The unique constraint on `lock_key` is the durable, race-safe
                // guard: even under a concurrent double-invocation, only one
                // transaction can successfully insert this row.
                $bootstrap = new SuperAdminBootstrap([
                    'lock_key' => SuperAdminBootstrap::LOCK_KEY,
                    'bootstrapped_at' => now(),
                ]);

                $user = User::create([
                    'email' => $email,
                    'password' => Hash::make($password),
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();

                $bootstrap->user_id = $user->id;
                $bootstrap->save();

                return $user;
            });
        } catch (\Throwable $e) {
            $this->error('Bootstrap failed — a Super Admin may already have been bootstrapped concurrently.');

            return self::FAILURE;
        }

        $audit->record('first_super_admin_bootstrap_completed', $user);

        $this->info('First Super Admin identity bootstrapped successfully.');
        $this->line('Canonical Super Admin authority (role/permission/scope) is granted separately by IMP-003.');

        return self::SUCCESS;
    }

    /**
     * @throws ValidationException
     */
    private function validateInput(string $email, string $password, string $confirmPassword): void
    {
        validator(
            ['email' => $email, 'password' => $password, 'password_confirmation' => $confirmPassword],
            [
                'email' => ['required', 'email'],
                'password' => ['required', 'confirmed', PasswordRule::min(12)],
            ]
        )->validate();
    }
}
