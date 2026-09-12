<?php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Q22 — self-registration mechanics shared by Donor and Fundraiser. Identical
 * mechanically: creates an Identity/Auth subject only. NEVER grants a role,
 * permission, scope, or business authority — a self-registered Fundraiser
 * receives no automatic Fundraiser business authority (IMP-003+ concern).
 *
 * Partner Representative, Internal Administrative Identity, and Super Admin do
 * NOT use this service — see InvitationService and the bootstrap console
 * command instead (Q22/Q25).
 */
class RegistrationService
{
    public function __construct(
        private readonly EmailNormalizer $normalizer,
        private readonly IdentityAuditLogger $audit,
    ) {}

    public function register(string $email, string $password): User
    {
        $normalized = $this->normalizer->normalize($email);

        $user = DB::transaction(function () use ($normalized, $password) {
            return User::create([
                'email' => $normalized,
                'password' => Hash::make($password),
            ]);
        });

        $this->audit->record('identity_created', $user);
        $this->audit->record('self_registration_completed', $user);

        event(new Registered($user));

        return $user;
    }
}
