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

        $permissionCodes = array_keys(PermissionRegistry::definitions());

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
