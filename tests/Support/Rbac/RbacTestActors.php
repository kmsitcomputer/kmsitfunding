<?php

namespace Tests\Support\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Identity\AssuranceService;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use Illuminate\Support\Facades\Hash;

/**
 * Test-only actor bootstrap, mirroring the Q25 Bridge's own pattern
 * (directly inserting the canonical `super_admin` grant with
 * `assigned_by_principal_id = null` — never through
 * `RoleAssignmentService`, which now enforces `IMP003-IMPL-M01`'s
 * permission + ELEVATED requirement and would otherwise make every fixture
 * "admin" Principal in these tests circular: needing rbac.role.assign to
 * grant rbac.role.assign to itself). This is the internal-setup bypass
 * `IMP003-IMPL-M03`'s "acceptable for seeding/internal setup, never an
 * exposed runtime path" carve-out explicitly allows.
 */
trait RbacTestActors
{
    private int $rbacTestActorSequence = 0;

    /**
     * A Principal holding every IMP-003-owned rbac.* / identity.security.transition
     * permission at GLOBAL_PLATFORM scope, AND an ELEVATED session — able to
     * pass every RbacMutationGuard check in this test process.
     */
    private function makeAuthorizedActor(): Principal
    {
        $this->rbacTestActorSequence++;

        $user = User::create([
            'email' => "rbac-test-actor-{$this->rbacTestActorSequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        $principal = app(PrincipalService::class)->forUser($user);

        $role = Role::firstOrCreate(
            ['code' => 'test_rbac_root'],
            ['name' => 'Test RBAC Root', 'is_system' => true],
        );

        foreach (array_keys(PermissionRegistry::definitions()) as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['description' => 'test']);

            $alreadyGranted = $role->permissions()->where('permissions.id', $permission->id)->exists();

            if (! $alreadyGranted) {
                $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
            }
        }

        PrincipalRoleAssignment::create([
            'principal_id' => $principal->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);

        app(AssuranceService::class)->elevate();

        return $principal;
    }

    /**
     * A plain, unprivileged Principal — holds no Role/Permission/Authority,
     * STANDARD assurance. Useful as an explicit negative-test actor.
     */
    private function makeUnauthorizedActor(): Principal
    {
        $this->rbacTestActorSequence++;

        $user = User::create([
            'email' => "rbac-test-plain-{$this->rbacTestActorSequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        return app(PrincipalService::class)->forUser($user);
    }
}
