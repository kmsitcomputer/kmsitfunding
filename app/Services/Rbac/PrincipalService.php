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
 * canonical `principals` row per User/System/Integration source, the
 * Canonical User Deletion Transaction (atomic — see "Principal Lifecycle"),
 * and canonical non-human Principal deactivation (`IMP003-IMPL-M04`).
 */
class PrincipalService
{
    public function __construct(
        private readonly RbacMutationGuard $guard,
        private readonly RbacAuditLogger $audit,
    ) {}

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

    /**
     * `IMP003-REAUDIT1-M02`: if $systemPrincipal is ALREADY deactivated, the
     * freshly-created Principal must never come back authorization-enabled
     * — a deactivated catalog identity can never have a live-looking
     * canonical Principal, regardless of which order creation and
     * deactivation happen to occur in.
     */
    public function forSystem(SystemPrincipal $systemPrincipal): Principal
    {
        return DB::transaction(function () use ($systemPrincipal) {
            $principal = Principal::firstOrCreate(
                ['system_principal_id' => $systemPrincipal->id],
                ['principal_kind' => PrincipalKind::System],
            );

            if ($principal->wasRecentlyCreated && $systemPrincipal->deactivated_at !== null) {
                $principal->forceFill(['disabled_at' => $systemPrincipal->deactivated_at])->save();
            }

            return $principal;
        });
    }

    /**
     * See `forSystem()` — identical invariant for Integration Principal.
     */
    public function forIntegration(IntegrationPrincipal $integrationPrincipal): Principal
    {
        return DB::transaction(function () use ($integrationPrincipal) {
            $principal = Principal::firstOrCreate(
                ['integration_principal_id' => $integrationPrincipal->id],
                ['principal_kind' => PrincipalKind::Integration],
            );

            if ($principal->wasRecentlyCreated && $integrationPrincipal->deactivated_at !== null) {
                $principal->forceFill(['disabled_at' => $integrationPrincipal->deactivated_at])->save();
            }

            return $principal;
        });
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

                $this->audit->record('human_principal_tombstoned_user_deleted', [
                    'acting_principal_id' => $actingPrincipal?->id,
                    'principal_id' => $principal->id,
                ]);
            }

            // Step 7: the User row is deleted — the FK (RESTRICT) has
            // nothing left to restrict, since no principals row references
            // this User's id any more.
            $lockedUser->delete();

            // Step 8: COMMIT (implicit — DB::transaction() commits on
            // successful return; any exception above triggers ROLLBACK).
        });
    }

    /**
     * Canonical System Principal deactivation (`IMP003-IMPL-M04`,
     * `IMP003-REAUDIT1-M02`) — a one-way transition. Within ONE
     * transaction: authorize the acting Principal, lock the catalog row,
     * ENSURE the canonical `principals` row exists (the same lazy/
     * idempotent creation `forSystem()` itself uses — a catalog identity
     * that was never explicitly resolved to a Principal before being
     * deactivated must still end up with one, disabled, rather than
     * silently deactivating only half the lifecycle), lock it, then set
     * `system_principals.deactivated_at` and `principals.disabled_at`
     * together. No reactivation is implemented — the specification does
     * not define one.
     */
    public function deactivateSystem(Principal $actor, SystemPrincipal $systemPrincipal): void
    {
        DB::transaction(function () use ($actor, $systemPrincipal) {
            $lockedActor = Principal::whereKey($actor->id)->lockForUpdate()->firstOrFail();

            $this->guard->ensureAuthorized($lockedActor, PermissionRegistry::RBAC_PRINCIPAL_DEACTIVATE);

            $lockedCatalog = SystemPrincipal::whereKey($systemPrincipal->id)->lockForUpdate()->firstOrFail();

            if ($lockedCatalog->deactivated_at !== null) {
                throw new \RuntimeException('System Principal is already deactivated.');
            }

            Principal::firstOrCreate(
                ['system_principal_id' => $lockedCatalog->id],
                ['principal_kind' => PrincipalKind::System],
            );
            $lockedPrincipal = Principal::where('system_principal_id', $lockedCatalog->id)->lockForUpdate()->firstOrFail();

            $now = now();

            $lockedCatalog->forceFill(['deactivated_at' => $now])->save();
            $lockedPrincipal->forceFill(['disabled_at' => $now])->save();

            $this->audit->record('non_human_principal_deactivated', [
                'actor_principal_id' => $lockedActor->id,
                'kind' => 'system',
                'system_principal_id' => $lockedCatalog->id,
                'principal_id' => $lockedPrincipal->id,
            ]);
        });
    }

    /**
     * Canonical Integration Principal deactivation — identical contract to
     * `deactivateSystem()`, see there for the full rationale.
     */
    public function deactivateIntegration(Principal $actor, IntegrationPrincipal $integrationPrincipal): void
    {
        DB::transaction(function () use ($actor, $integrationPrincipal) {
            $lockedActor = Principal::whereKey($actor->id)->lockForUpdate()->firstOrFail();

            $this->guard->ensureAuthorized($lockedActor, PermissionRegistry::RBAC_PRINCIPAL_DEACTIVATE);

            $lockedCatalog = IntegrationPrincipal::whereKey($integrationPrincipal->id)->lockForUpdate()->firstOrFail();

            if ($lockedCatalog->deactivated_at !== null) {
                throw new \RuntimeException('Integration Principal is already deactivated.');
            }

            Principal::firstOrCreate(
                ['integration_principal_id' => $lockedCatalog->id],
                ['principal_kind' => PrincipalKind::Integration],
            );
            $lockedPrincipal = Principal::where('integration_principal_id', $lockedCatalog->id)->lockForUpdate()->firstOrFail();

            $now = now();

            $lockedCatalog->forceFill(['deactivated_at' => $now])->save();
            $lockedPrincipal->forceFill(['disabled_at' => $now])->save();

            $this->audit->record('non_human_principal_deactivated', [
                'actor_principal_id' => $lockedActor->id,
                'kind' => 'integration',
                'integration_principal_id' => $lockedCatalog->id,
                'principal_id' => $lockedPrincipal->id,
            ]);
        });
    }
}
