<?php

namespace Tests\Feature\Rbac;

use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Identity\AssuranceService;
use App\Services\Rbac\PrincipalService;
use App\Services\Rbac\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * `IMP003-IMPL-M03` — the canonical, authoritative Role-Permission
 * grant/revoke mutation service: authorization + ELEVATED assurance +
 * self-expansion protection + durable history + audit.
 */
class RolePermissionServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private int $userSequence = 0;

    private function makePrincipal(): Principal
    {
        $this->userSequence++;
        $user = User::create([
            'email' => "role-permission-test-{$this->userSequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        return app(PrincipalService::class)->forUser($user);
    }

    public function test_grant_without_permission_is_denied(): void
    {
        $actor = $this->makePrincipal();
        $role = Role::create(['code' => 'rp_role_1', 'name' => 'RP Role 1']);
        $permission = Permission::create(['code' => 'rp.test.perm1', 'description' => 'test']);

        $this->expectException(\RuntimeException::class);

        app(RolePermissionService::class)->grant($actor, $role, $permission);
    }

    public function test_grant_without_elevated_assurance_is_denied(): void
    {
        $actor = $this->makeAuthorizedActor();
        app(AssuranceService::class)->invalidate();
        $role = Role::create(['code' => 'rp_role_2', 'name' => 'RP Role 2']);
        $permission = Permission::create(['code' => 'rp.test.perm2', 'description' => 'test']);

        $this->expectException(\RuntimeException::class);

        app(RolePermissionService::class)->grant($actor, $role, $permission);
    }

    public function test_revoke_without_permission_is_denied(): void
    {
        $actor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'rp_role_3', 'name' => 'RP Role 3']);
        $permission = Permission::create(['code' => 'rp.test.perm3', 'description' => 'test']);
        app(RolePermissionService::class)->grant($actor, $role, $permission);

        $noPermissionActor = $this->makePrincipal();

        $this->expectException(\RuntimeException::class);

        app(RolePermissionService::class)->revoke($noPermissionActor, $role, $permission);
    }

    public function test_self_expansion_via_role_permission_grant_is_denied(): void
    {
        $actor = $this->makeAuthorizedActor();

        // The actor already holds `test_rbac_root` (granted in
        // makeAuthorizedActor()) — attempting to grant that SAME role a new
        // Permission would indirectly self-expand the actor's own effective
        // privilege the instant this commits.
        $selfRole = Role::where('code', 'test_rbac_root')->firstOrFail();
        $newPermission = Permission::create(['code' => 'rp.test.self_expansion', 'description' => 'test']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Self-Escalation Protection');

        app(RolePermissionService::class)->grant($actor, $selfRole, $newPermission);
    }

    public function test_authorized_grant_and_revoke_succeed_with_durable_history(): void
    {
        $actor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'rp_role_4', 'name' => 'RP Role 4']);
        $permission = Permission::create(['code' => 'rp.test.perm4', 'description' => 'test']);

        app(RolePermissionService::class)->grant($actor, $role, $permission);

        $this->assertTrue($role->permissions()->where('permissions.id', $permission->id)->exists());

        app(RolePermissionService::class)->revoke($actor, $role, $permission);

        $role->refresh();
        $this->assertFalse($role->permissions()->where('permissions.id', $permission->id)->exists());

        $this->assertSame(
            1,
            DB::table('role_permissions')->where('role_id', $role->id)->where('permission_id', $permission->id)->count(),
            'The historical grant row must remain (durable history, not hard-deleted).'
        );

        $historyRow = DB::table('role_permissions')->where('role_id', $role->id)->where('permission_id', $permission->id)->first();
        $this->assertSame($actor->id, $historyRow->granted_by_principal_id);
        $this->assertSame($actor->id, $historyRow->revoked_by_principal_id);
        $this->assertNotNull($historyRow->revoked_at);
    }

    public function test_grant_is_idempotent_for_an_already_active_grant(): void
    {
        $actor = $this->makeAuthorizedActor();
        $role = Role::create(['code' => 'rp_role_5', 'name' => 'RP Role 5']);
        $permission = Permission::create(['code' => 'rp.test.perm5', 'description' => 'test']);

        app(RolePermissionService::class)->grant($actor, $role, $permission);
        app(RolePermissionService::class)->grant($actor, $role, $permission);

        $this->assertSame(
            1,
            DB::table('role_permissions')->where('role_id', $role->id)->where('permission_id', $permission->id)->whereNull('revoked_at')->count(),
        );
    }
}
