<?php

namespace Tests\Feature\Rbac;

use App\Enums\IdentityLifecycle;
use App\Enums\ScopeType;
use App\Enums\SecurityRestriction;
use App\Models\Rbac\AuthorityType;
use App\Models\Rbac\IntegrationPrincipal;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\Rbac\SystemPrincipal;
use App\Models\User;
use App\Services\Identity\AssuranceService;
use App\Services\Rbac\AuthorityAssignmentService;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use App\Services\Rbac\RoleAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * `IMP003-IMPL-M01` — every Role/Authority assignment/revocation mutation
 * must enforce, at the authoritative service layer (never left to a
 * caller), that the acting Principal is locked, eligible, holds the
 * operation's required permission, and carries ELEVATED assurance.
 */
class MutationAuthorizationTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private int $userSequence = 0;

    private function makeUser(): User
    {
        $this->userSequence++;

        return User::create([
            'email' => "mutation-auth-test-{$this->userSequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
    }

    private function makePrincipal(): Principal
    {
        return app(PrincipalService::class)->forUser($this->makeUser());
    }

    /**
     * Directly (bypassing RoleAssignmentService) grants $principal a
     * management permission at GLOBAL_PLATFORM scope — used only to isolate
     * a DIFFERENT denial cause (e.g. deactivation) from "missing permission"
     * in a negative test.
     */
    private function grantManagementPermissionDirectly(Principal $principal, string $permissionCode): void
    {
        $role = Role::create(['code' => 'direct_grant_'.$principal->id, 'name' => 'Direct Grant']);
        $permission = Permission::firstOrCreate(['code' => $permissionCode], ['description' => 'test']);
        $role->permissions()->attach($permission->id, ['granted_at' => now()]);

        PrincipalRoleAssignment::create([
            'principal_id' => $principal->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);
    }

    private function financialApprover(): AuthorityType
    {
        return AuthorityType::firstOrCreate(
            ['code' => AuthorityType::FINANCIAL_APPROVER],
            ['name' => 'Financial Approver', 'description' => 'test', 'is_financial' => true],
        );
    }

    // --- Role assignment ---

    public function test_role_assignment_without_permission_is_denied(): void
    {
        $noPermissionActor = $this->makePrincipal();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'mut_role_1', 'name' => 'Mut Role 1']);

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->assign($noPermissionActor, $target, $role, ScopeType::GlobalPlatform, null);
    }

    public function test_role_assignment_with_permission_but_standard_assurance_is_denied(): void
    {
        $actor = $this->makeAuthorizedActor();
        app(AssuranceService::class)->invalidate();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'mut_role_2', 'name' => 'Mut Role 2']);

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);
    }

    public function test_role_assignment_by_authorized_elevated_actor_succeeds(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'mut_role_3', 'name' => 'Mut Role 3']);

        $assignment = app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);

        $this->assertTrue($assignment->isActive());
    }

    public function test_role_assignment_by_disabled_actor_is_denied(): void
    {
        $actor = $this->makeAuthorizedActor();
        $actor->humanUser->forceFill(['lifecycle_state' => IdentityLifecycle::Disabled])->save();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'mut_role_4', 'name' => 'Mut Role 4']);

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);
    }

    public function test_role_assignment_by_suspended_actor_is_denied(): void
    {
        $actor = $this->makeAuthorizedActor();
        $actor->humanUser->forceFill(['security_restriction' => SecurityRestriction::Suspended])->save();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'mut_role_5', 'name' => 'Mut Role 5']);

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);
    }

    public function test_role_assignment_by_tombstoned_actor_is_denied(): void
    {
        $actor = $this->makeAuthorizedActor();
        $actorUser = $actor->humanUser;
        $bystander = $this->makeAuthorizedActor();
        app(PrincipalService::class)->deleteUser($actorUser, $bystander);

        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'mut_role_6', 'name' => 'Mut Role 6']);

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);
    }

    public function test_role_assignment_by_deactivated_system_actor_is_denied(): void
    {
        $systemRow = SystemPrincipal::create(['code' => 'mut_test_system']);
        $actor = app(PrincipalService::class)->forSystem($systemRow);
        $this->grantManagementPermissionDirectly($actor, PermissionRegistry::RBAC_ROLE_ASSIGN);
        app(AssuranceService::class)->elevate();

        // Deactivate AFTER granting the permission, so the failure below is
        // isolated to deactivation, not "missing permission".
        $systemRow->forceFill(['deactivated_at' => now()])->save();

        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'mut_role_7', 'name' => 'Mut Role 7']);

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);
    }

    public function test_role_assignment_by_deactivated_integration_actor_is_denied(): void
    {
        $integrationRow = IntegrationPrincipal::create(['code' => 'mut_test_integration']);
        $actor = app(PrincipalService::class)->forIntegration($integrationRow);
        $this->grantManagementPermissionDirectly($actor, PermissionRegistry::RBAC_ROLE_ASSIGN);
        app(AssuranceService::class)->elevate();

        $integrationRow->forceFill(['deactivated_at' => now()])->save();

        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'mut_role_8', 'name' => 'Mut Role 8']);

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);
    }

    // --- Role revocation ---

    public function test_role_revocation_without_permission_is_denied(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'mut_role_9', 'name' => 'Mut Role 9']);
        $assignment = app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);

        $noPermissionActor = $this->makePrincipal();

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->revoke($noPermissionActor, $assignment);
    }

    public function test_role_revocation_with_standard_assurance_is_denied(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'mut_role_10', 'name' => 'Mut Role 10']);
        $assignment = app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);

        app(AssuranceService::class)->invalidate();

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->revoke($actor, $assignment);
    }

    public function test_role_revocation_by_authorized_elevated_actor_succeeds(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'mut_role_11', 'name' => 'Mut Role 11']);
        $assignment = app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);

        app(RoleAssignmentService::class)->revoke($actor, $assignment);

        $assignment->refresh();
        $this->assertNotNull($assignment->revoked_at);
        $this->assertSame($actor->id, $assignment->revoked_by_principal_id);
    }

    // --- Authority assignment/revocation ---

    public function test_authority_assignment_without_permission_is_denied(): void
    {
        $noPermissionActor = $this->makePrincipal();
        $target = $this->makePrincipal();

        $this->expectException(\RuntimeException::class);

        app(AuthorityAssignmentService::class)->assign($noPermissionActor, $target, $this->financialApprover(), ScopeType::GlobalPlatform, null);
    }

    public function test_authority_assignment_with_standard_assurance_is_denied(): void
    {
        $actor = $this->makeAuthorizedActor();
        app(AssuranceService::class)->invalidate();
        $target = $this->makePrincipal();

        $this->expectException(\RuntimeException::class);

        app(AuthorityAssignmentService::class)->assign($actor, $target, $this->financialApprover(), ScopeType::GlobalPlatform, null);
    }

    public function test_authority_assignment_by_authorized_elevated_actor_succeeds(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();

        $assignment = app(AuthorityAssignmentService::class)->assign($actor, $target, $this->financialApprover(), ScopeType::GlobalPlatform, null);

        $this->assertTrue($assignment->isActive());
    }

    public function test_authority_revocation_without_permission_is_denied(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $assignment = app(AuthorityAssignmentService::class)->assign($actor, $target, $this->financialApprover(), ScopeType::GlobalPlatform, null);

        $noPermissionActor = $this->makePrincipal();

        $this->expectException(\RuntimeException::class);

        app(AuthorityAssignmentService::class)->revoke($noPermissionActor, $assignment);
    }

    public function test_authority_revocation_by_authorized_elevated_actor_succeeds(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $assignment = app(AuthorityAssignmentService::class)->assign($actor, $target, $this->financialApprover(), ScopeType::GlobalPlatform, null);

        app(AuthorityAssignmentService::class)->revoke($actor, $assignment);

        $assignment->refresh();
        $this->assertNotNull($assignment->revoked_at);
        $this->assertSame($actor->id, $assignment->revoked_by_principal_id);
    }
}
