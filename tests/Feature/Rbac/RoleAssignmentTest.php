<?php

namespace Tests\Feature\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Rbac\PrincipalService;
use App\Services\Rbac\RoleAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private int $userSequence = 0;

    private function makePrincipal(): Principal
    {
        $this->userSequence++;
        $user = User::create([
            'email' => "role-assign-test-{$this->userSequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        return app(PrincipalService::class)->forUser($user);
    }

    public function test_self_role_assignment_is_denied(): void
    {
        $principal = $this->makePrincipal();
        $role = Role::create(['code' => 'r1', 'name' => 'R1']);

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->assign($principal, $principal, $role, ScopeType::GlobalPlatform, null);
    }

    public function test_scope_id_must_be_null_for_global_platform(): void
    {
        $grantor = $this->makePrincipal();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'r2', 'name' => 'R2']);

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->assign($grantor, $target, $role, ScopeType::GlobalPlatform, 1);
    }

    public function test_scope_id_is_required_for_partner_scope(): void
    {
        $grantor = $this->makePrincipal();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'r3', 'name' => 'R3']);

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->assign($grantor, $target, $role, ScopeType::Partner, null);
    }

    public function test_renewal_revokes_the_prior_identical_assignment_before_inserting_the_new_one(): void
    {
        $grantor = $this->makePrincipal();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'r4', 'name' => 'R4']);
        $service = app(RoleAssignmentService::class);

        $first = $service->assign($grantor, $target, $role, ScopeType::GlobalPlatform, null);
        $second = $service->assign($grantor, $target, $role, ScopeType::GlobalPlatform, null);

        $first->refresh();
        $this->assertNotNull($first->revoked_at);
        $this->assertNull($second->revoked_at);
        $this->assertSame(
            1,
            PrincipalRoleAssignment::where('principal_id', $target->id)
                ->where('role_id', $role->id)
                ->whereNull('revoked_at')
                ->count()
        );
    }

    public function test_active_assignment_uniqueness_is_enforced_without_now_dependence(): void
    {
        $grantor = $this->makePrincipal();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'r5', 'name' => 'R5']);

        // Two concurrent-looking assigns to the identical (principal, role, scope) slot
        // must never produce two simultaneously-active rows — verified by inspecting the
        // generated active_assignment_key unique index directly rather than the service
        // (which already revokes-then-inserts); this asserts the DB-level guarantee itself.
        $a = PrincipalRoleAssignment::create([
            'principal_id' => $target->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => $grantor->id,
        ]);

        $this->expectException(QueryException::class);

        PrincipalRoleAssignment::create([
            'principal_id' => $target->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => $grantor->id,
        ]);
    }

    public function test_expired_assignment_is_not_active(): void
    {
        $grantor = $this->makePrincipal();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'r6', 'name' => 'R6']);

        $assignment = PrincipalRoleAssignment::create([
            'principal_id' => $target->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'assigned_by_principal_id' => $grantor->id,
        ]);

        $this->assertFalse($assignment->isActive());
    }

    public function test_future_assignment_is_not_yet_active(): void
    {
        $grantor = $this->makePrincipal();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'r7', 'name' => 'R7']);

        $assignment = PrincipalRoleAssignment::create([
            'principal_id' => $target->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now()->addDay(),
            'assigned_by_principal_id' => $grantor->id,
        ]);

        $this->assertFalse($assignment->isActive());
    }

    public function test_revoked_assignment_is_not_active(): void
    {
        $grantor = $this->makePrincipal();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'r8', 'name' => 'R8']);
        $service = app(RoleAssignmentService::class);

        $assignment = $service->assign($grantor, $target, $role, ScopeType::GlobalPlatform, null);
        $service->revoke($grantor, $assignment);

        $assignment->refresh();
        $this->assertFalse($assignment->isActive());
    }

    public function test_tombstoned_principal_cannot_receive_a_role_assignment(): void
    {
        $grantor = $this->makePrincipal();
        $targetUser = User::create(['email' => 'tombstone-target@example.com', 'password' => Hash::make('correct-horse-battery-staple')]);
        $target = app(PrincipalService::class)->forUser($targetUser);
        app(PrincipalService::class)->deleteUser($targetUser, $grantor);

        $role = Role::create(['code' => 'r9', 'name' => 'R9']);

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->assign($grantor, $target, $role, ScopeType::GlobalPlatform, null);
    }

    public function test_tombstoned_principal_cannot_act_as_grantor(): void
    {
        $grantorUser = User::create(['email' => 'tombstone-grantor@example.com', 'password' => Hash::make('correct-horse-battery-staple')]);
        $grantor = app(PrincipalService::class)->forUser($grantorUser);
        $bystander = $this->makePrincipal();
        app(PrincipalService::class)->deleteUser($grantorUser, $bystander);

        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'r10', 'name' => 'R10']);

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->assign($grantor, $target, $role, ScopeType::GlobalPlatform, null);
    }

    public function test_role_permission_grant_history_is_durable(): void
    {
        $role = Role::create(['code' => 'r11', 'name' => 'R11']);
        $permission = Permission::create(['code' => 'test.permission.r11', 'description' => 'test']);

        $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        $this->assertTrue($role->permissions()->where('permissions.id', $permission->id)->exists());

        $role->permissions()->updateExistingPivot($permission->id, ['revoked_at' => now()]);

        $role->refresh();
        $this->assertFalse($role->permissions()->where('permissions.id', $permission->id)->exists(), 'wherePivotNull(revoked_at) must exclude the revoked grant.');
        $this->assertSame(
            1,
            DB::table('role_permissions')->where('role_id', $role->id)->where('permission_id', $permission->id)->count(),
            'The historical grant row itself must remain (durable history, not hard-deleted).'
        );
    }
}
