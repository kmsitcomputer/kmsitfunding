<?php

namespace App\Services\Rbac;

use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use Illuminate\Support\Facades\DB;

/**
 * The canonical, authoritative Role -> Permission grant/revoke mutation
 * service (`IMP003-IMPL-M03`, `IMP003-REAUDIT1-M01`). Application code must
 * never treat `$role->permissions()->attach()/detach()/sync()` or a raw
 * `role_permissions` table write as the authorized business interface for a
 * runtime request — this service is that interface. Direct pivot mutation
 * remains acceptable ONLY for internal setup/seeding code that runs with no
 * acting Principal at all (e.g. `RbacRoleSeeder`'s initial `super_admin`
 * grants, attributed to `granted_by_principal_id = null`), never as a
 * runtime-reachable path.
 */
class RolePermissionService
{
    public function __construct(
        private readonly RbacMutationGuard $guard,
        private readonly RbacAuditLogger $audit,
    ) {}

    /**
     * Lock order for a NEW grant: acting Principal -> target Role (reloaded
     * + lifecycle-validated) -> target Permission (reloaded + lifecycle-
     * validated) -> active grant slot -> mutation -> audit -> commit. A
     * retired Role or a deprecated Permission never receives a NEW grant
     * (`IMP003-REAUDIT1-M01`) — retirement/deprecation only ever blocks NEW
     * assignments, never retroactively revokes an existing one (see
     * "Permission Registry").
     */
    public function grant(Principal $actor, Role $role, Permission $permission): void
    {
        DB::transaction(function () use ($actor, $role, $permission) {
            $lockedActor = Principal::whereKey($actor->id)->lockForUpdate()->firstOrFail();

            $this->guard->ensureAuthorized($lockedActor, PermissionRegistry::RBAC_PERMISSION_ASSIGN);

            // Reload + lock the target Role from the DB — a caller-supplied
            // (possibly stale) Role instance must never be trusted for the
            // retirement check below.
            $lockedRole = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();

            if ($lockedRole->retired_at !== null) {
                throw new \RuntimeException(
                    "Role '{$lockedRole->code}' is retired — no new Permission grant is allowed."
                );
            }

            // Same reload + lock discipline for the target Permission.
            $lockedPermission = Permission::whereKey($permission->id)->lockForUpdate()->firstOrFail();

            if ($lockedPermission->deprecated_at !== null) {
                throw new \RuntimeException(
                    "Permission '{$lockedPermission->code}' is deprecated — no new grant is allowed."
                );
            }

            // Approved conditional Self-Escalation rule (see "Role ->
            // Permission Assignment"): granting a Permission to a Role the
            // actor itself holds is denied ONLY when that grant would
            // actually expand the actor's own effective authority — i.e.
            // only when the actor does not ALREADY effectively hold that
            // Permission (directly or via any other currently-assigned
            // Role). If the actor already effectively has it, this grant
            // changes nothing about their own authority and may proceed.
            $actorAlreadyHasPermission = (new AuthorizationContext($lockedActor))
                ->activeRoleAssignmentsGranting($lockedPermission->code)
                ->isNotEmpty();

            if (! $actorAlreadyHasPermission) {
                // `IMP003-REAUDIT1-M01`/`R3-M01`: "currently holds" means
                // effective right now — the same canonical temporal
                // predicate `PrincipalRoleAssignment::isActive()` already
                // defines (not revoked, started, not yet ended). A merely
                // historical/expired/revoked/future assignment must never
                // trigger this branch.
                $actorHoldsTargetRole = PrincipalRoleAssignment::where('principal_id', $lockedActor->id)
                    ->where('role_id', $lockedRole->id)
                    ->whereNull('revoked_at')
                    ->get()
                    ->contains(fn (PrincipalRoleAssignment $assignment) => $assignment->isActive());

                if ($actorHoldsTargetRole) {
                    throw new \RuntimeException(
                        "Granting Permission '{$lockedPermission->code}', which the acting Principal does ".
                        "not already effectively hold, to Role '{$lockedRole->code}' — a Role that Principal ".
                        'itself holds — is denied (Self-Escalation Protection: this grant would expand the '.
                        "actor's own effective authority)."
                    );
                }
            }

            $alreadyActive = DB::table('role_permissions')
                ->where('role_id', $lockedRole->id)
                ->where('permission_id', $lockedPermission->id)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->exists();

            if ($alreadyActive) {
                return;
            }

            $now = now();

            DB::table('role_permissions')->insert([
                'role_id' => $lockedRole->id,
                'permission_id' => $lockedPermission->id,
                'granted_at' => $now,
                'granted_by_principal_id' => $lockedActor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->record('role_permission_granted', [
                'actor_principal_id' => $lockedActor->id,
                'role_id' => $lockedRole->id,
                'permission_id' => $lockedPermission->id,
            ]);
        });
    }

    /**
     * Revocation is NOT blocked by Role retirement or Permission
     * deprecation — those states only ever prevent a NEW grant (see
     * `grant()`); closing an existing historical grant (e.g. security
     * cleanup) must remain possible regardless of either lifecycle state.
     * Target Role/Permission are still reloaded + locked (defense against a
     * stale caller-supplied model), just never lifecycle-rejected here.
     */
    public function revoke(Principal $actor, Role $role, Permission $permission): void
    {
        DB::transaction(function () use ($actor, $role, $permission) {
            $lockedActor = Principal::whereKey($actor->id)->lockForUpdate()->firstOrFail();

            $this->guard->ensureAuthorized($lockedActor, PermissionRegistry::RBAC_PERMISSION_REVOKE);

            $lockedRole = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            $lockedPermission = Permission::whereKey($permission->id)->lockForUpdate()->firstOrFail();

            $now = now();

            $updated = DB::table('role_permissions')
                ->where('role_id', $lockedRole->id)
                ->where('permission_id', $lockedPermission->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $now,
                    'revoked_by_principal_id' => $lockedActor->id,
                    'updated_at' => $now,
                ]);

            if ($updated > 0) {
                $this->audit->record('role_permission_revoked', [
                    'actor_principal_id' => $lockedActor->id,
                    'role_id' => $lockedRole->id,
                    'permission_id' => $lockedPermission->id,
                ]);
            }
        });
    }
}
