<?php

namespace App\Services\Identity;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * M02 — invitation lifecycle for Partner Representative, Internal
 * Administrative Identity, and Super Admin (Q22). See "Invitation Boundary" in
 * docs/implementation/IMP-002-identity-authentication.md.
 *
 * The issuer/revoker reference is attribution only — it does NOT itself imply
 * role authority (an IMP-003+ concern). Acceptance creates an Identity/Auth
 * subject ONLY; it never grants a role, permission, or business authority.
 */
class InvitationService
{
    public const ACTOR_PARTNER_REPRESENTATIVE = 'partner_representative';

    public const ACTOR_INTERNAL_ADMINISTRATIVE_IDENTITY = 'internal_administrative_identity';

    public const ACTOR_SUPER_ADMIN = 'super_admin';

    public function __construct(
        private readonly EmailNormalizer $normalizer,
        private readonly IdentityAuditLogger $audit,
    ) {}

    /**
     * @return array{invitation: Invitation, plain_token: string}
     */
    public function issue(string $invitedActor, string $intendedEmail, ?User $issuer): array
    {
        $normalized = $this->normalizer->normalize($intendedEmail);
        $plainToken = Str::random(64);

        // IMP-004: invitation_issued is CRITICAL/MUTATION_ATOMIC — creation
        // and the canonical audit append commit as ONE transaction (Q26), and
        // the event requires a resolved issuer Principal actor (fail-closed).
        $invitation = DB::transaction(function () use ($normalized, $plainToken, $invitedActor, $issuer) {
            $invitation = Invitation::create([
                'intended_email' => $normalized,
                'token_hash' => Hash::make($plainToken),
                'invited_actor' => $invitedActor,
                'issued_at' => now(),
                'expires_at' => now()->addDays((int) config('identity.invitation_ttl_days')),
                'issuer_user_id' => $issuer?->id,
            ]);

            $this->audit->record('invitation_issued', $issuer, [
                'invitation_id' => $invitation->id,
                'invitation_public_id' => $invitation->public_id,
                'invited_actor' => $invitedActor,
            ]);

            return $invitation;
        });

        return ['invitation' => $invitation, 'plain_token' => $plainToken];
    }

    public function revoke(Invitation $invitation, ?User $revoker): bool
    {
        return DB::transaction(function () use ($invitation, $revoker) {
            // IMP002-IMPL-M08: lock the row exactly like accept() does, so
            // acceptance and revocation cannot race each other into both
            // succeeding — whichever terminal transition's transaction commits
            // first wins, and the other re-checks isActive() under the same
            // lock and fails safely.
            /** @var Invitation|null $locked */
            $locked = Invitation::where('id', $invitation->id)->lockForUpdate()->first();

            if ($locked === null || ! $locked->isActive()) {
                return false;
            }

            $locked->forceFill([
                'revoked_at' => now(),
                'revoker_user_id' => $revoker?->id,
            ])->save();

            $this->audit->record('invitation_revoked', $revoker, [
                'invitation_id' => $locked->id,
                'invitation_public_id' => $locked->public_id,
            ]);

            return true;
        });
    }

    /**
     * @return array{status: 'accepted'|'invalid', user?: User}
     */
    public function accept(Invitation $invitation, string $plainToken, string $presentedEmail, string $password): array
    {
        $normalizedPresented = $this->normalizer->normalize($presentedEmail);

        return DB::transaction(function () use ($invitation, $plainToken, $normalizedPresented, $password) {
            /** @var Invitation|null $locked */
            $locked = Invitation::where('id', $invitation->id)->lockForUpdate()->first();

            if ($locked === null
                || ! $locked->isActive()
                || ! Hash::check($plainToken, $locked->token_hash)
                || $locked->intended_email !== $normalizedPresented
            ) {
                return ['status' => 'invalid'];
            }

            if (User::where('email', $normalizedPresented)->exists()) {
                // Do not silently duplicate an identity — see "Invitation /
                // Registration Email-Uniqueness Race". Treated as invalid;
                // reconciliation is an owning-workflow concern beyond IMP-002.
                return ['status' => 'invalid'];
            }

            $user = User::create([
                'email' => $normalizedPresented,
                'password' => Hash::make($password),
            ]);

            $locked->forceFill([
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
            ])->save();

            $this->audit->record('identity_created', $user);
            $this->audit->record('invitation_accepted', $user, [
                'invitation_id' => $locked->id,
                'invitation_public_id' => $locked->public_id,
                'invited_actor' => $locked->invited_actor,
            ]);

            return ['status' => 'accepted', 'user' => $user];
        });
    }
}
