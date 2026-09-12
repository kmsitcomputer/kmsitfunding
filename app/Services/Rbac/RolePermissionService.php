<?php

namespace App\Services\Rbac;

use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use Illuminate\Support\Facades\DB;

/**
 * The canonical, authoritative Role -> Permission grant/revoke mutation
 * service (`IMP003-IMPL-M03`). Application code must never treat
 * `$role->permissions()->attach()/detach()/sync()` or a raw
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

    public function grant(Principal $actor, Role $role, Permission $permission): void
    {
        DB::transaction(function () use ($actor, $role, $permission) {
            $lockedActor = Principal::whereKey($actor->id)->lockForUpdate()->firstOrFail();

            $this->guard->ensureAuthorized($lockedActor, PermissionRegistry::RBAC_PERMISSION_ASSIGN);

            // Self-expansion protection: granting a Permission to a Role the
            // acting Principal itself currently holds would let it acquire
            // that Permission's effective privilege without a distinct
            // grantor — the same absolute rule as Self Role
            // Assignment/Self Authority Grant, applied to this indirect path.
            $actorHoldsRole = PrincipalRoleAssignment::where('principal_id', $lockedActor->id)
                ->where('role_id', $role->id)
                ->whereNull('revoked_at')
                ->exists();

            if ($actorHoldsRole) {
                throw new \RuntimeException(
                    'Granting a Permission to a Role the acting Principal itself holds is denied '.
                    '(Self-Escalation Protection — indirect self-expansion via Role-Permission grant).'
                );
            }

            $lockedRole = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();

            $alreadyActive = DB::table('role_permissions')
                ->where('role_id', $lockedRole->id)
                ->where('permission_id', $permission->id)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->exists();

            if ($alreadyActive) {
                return;
            }

            $now = now();

            DB::table('role_permissions')->insert([
                'role_id' => $lockedRole->id,
                'permission_id' => $permission->id,
                'granted_at' => $now,
                'granted_by_principal_id' => $lockedActor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->record('role_permission_granted', [
                'actor_principal_id' => $lockedActor->id,
                'role_id' => $lockedRole->id,
                'permission_id' => $permission->id,
            ]);
        });
    }

    public function revoke(Principal $actor, Role $role, Permission $permission): void
    {
        DB::transaction(function () use ($actor, $role, $permission) {
            $lockedActor = Principal::whereKey($actor->id)->lockForUpdate()->firstOrFail();

            $this->guard->ensureAuthorized($lockedActor, PermissionRegistry::RBAC_PERMISSION_REVOKE);

            $lockedRole = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();

            $now = now();

            $updated = DB::table('role_permissions')
                ->where('role_id', $lockedRole->id)
                ->where('permission_id', $permission->id)
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
                    'permission_id' => $permission->id,
                ]);
            }
        });
    }
}
