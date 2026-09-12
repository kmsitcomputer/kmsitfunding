<?php

namespace App\Services\Rbac;

use App\Enums\PrincipalKind;
use App\Models\Rbac\AuthorityAssignment;
use App\Models\Rbac\IntegrationPrincipal;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\SystemPrincipal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * IMP-003 Principal lifecycle service: idempotent creation/lookup of the
 * canonical `principals` row per User/System/Integration source, and the
 * Canonical User Deletion Transaction (atomic — see "Principal Lifecycle").
 */
class PrincipalService
{
    /**
     * Idempotently ensures a `principals` row exists for this User. Creating
     * it grants NOTHING — it is an empty identity shell until an assignment
     * references it (per "No Automatic Role at Registration").
     */
    public function forUser(User $user): Principal
    {
        return Principal::firstOrCreate(
            ['human_user_id' => $user->id],
            ['principal_kind' => PrincipalKind::Human],
        );
    }

    public function forSystem(SystemPrincipal $systemPrincipal): Principal
    {
        return Principal::firstOrCreate(
            ['system_principal_id' => $systemPrincipal->id],
            ['principal_kind' => PrincipalKind::System],
        );
    }

    public function forIntegration(IntegrationPrincipal $integrationPrincipal): Principal
    {
        return Principal::firstOrCreate(
            ['integration_principal_id' => $integrationPrincipal->id],
            ['principal_kind' => PrincipalKind::Integration],
        );
    }

    /**
     * The Canonical User Deletion Transaction — atomic. Either every step
     * (lock, revoke all active assignments, tombstone the principal, delete
     * the User) commits together, or none of it does. See "Principal
     * Lifecycle > Canonical User Deletion Transaction".
     *
     * $actingPrincipal is nullable (e.g. a self-service account-deletion
     * flow with no separate administrator) — recorded as the revoker on
     * every assignment this transaction revokes.
     */
    public function deleteUser(User $user, ?Principal $actingPrincipal = null): void
    {
        DB::transaction(function () use ($user, $actingPrincipal) {
            // Step 1: lock the User row.
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            // Step 2: lock the linked principals row, if one exists.
            $principal = Principal::where('human_user_id', $lockedUser->id)->lockForUpdate()->first();

            if ($principal !== null) {
                // Step 3: validate deletion preconditions.
                if ($principal->isTombstoned()) {
                    throw new \RuntimeException('Principal is already tombstoned.');
                }

                // Steps 4-5: revoke every currently-active assignment.
                $revokedAt = now();

                PrincipalRoleAssignment::where('principal_id', $principal->id)
                    ->whereNull('revoked_at')
                    ->update([
                        'revoked_at' => $revokedAt,
                        'revoked_by_principal_id' => $actingPrincipal?->id,
                    ]);

                AuthorityAssignment::where('principal_id', $principal->id)
                    ->whereNull('revoked_at')
                    ->update([
                        'revoked_at' => $revokedAt,
                        'revoked_by_principal_id' => $actingPrincipal?->id,
                    ]);

                // Step 6: ONE statement sets both tombstoned_at and
                // human_user_id together.
                $principal->forceFill([
                    'tombstoned_at' => $revokedAt,
                    'human_user_id' => null,
                ])->save();
            }

            // Step 7: the User row is deleted — the FK (RESTRICT) has
            // nothing left to restrict, since no principals row references
            // this User's id any more.
            $lockedUser->delete();

            // Step 8: COMMIT (implicit — DB::transaction() commits on
            // successful return; any exception above triggers ROLLBACK).
        });
    }
}
