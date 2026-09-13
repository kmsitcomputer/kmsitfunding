<?php

namespace Database\Seeders;

use App\Models\Rbac\Permission;
use App\Models\Rbac\Role;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Database\Seeder;

/**
 * Seeds ONLY the `super_admin` system Role, with the `rbac.*` +
 * `identity.security.transition` permissions this stage itself requires. No
 * `donor`/`fundraiser` Role is seeded (IMP003-READY-M05 — Identity Creation
 * != Role Assignment) and no other operational Role is invented — those are
 * later domain stages' own concern.
 */
class RbacRoleSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::updateOrCreate(
            ['code' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Canonical platform Super Admin role.', 'is_system' => true],
        );

        // IMP-004 (Q27): the audit.read.* family is deliberately EXCLUDED
        // from the super_admin bulk grant — Super Admin receives no automatic
        // audit access merely because of role name; each audit.read.*
        // permission requires its own explicit grant via RolePermissionService.
        $permissionCodes = array_filter(
            array_keys(PermissionRegistry::definitions()),
            fn (string $code) => ! str_starts_with($code, 'audit.'),
        );

        foreach (Permission::whereIn('code', $permissionCodes)->get() as $permission) {
            $alreadyGranted = $superAdmin->permissions()->where('permissions.id', $permission->id)->exists();

            if (! $alreadyGranted) {
                $superAdmin->permissions()->attach($permission->id, [
                    'granted_at' => now(),
                    'granted_by_principal_id' => null,
                ]);
            }
        }
    }
}
