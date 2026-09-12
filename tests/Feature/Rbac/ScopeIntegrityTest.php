<?php

namespace Tests\Feature\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\AuthorityType;
use App\Models\Rbac\Principal;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Rbac\AuthorityAssignmentService;
use App\Services\Rbac\PrincipalService;
use App\Services\Rbac\RoleAssignmentService;
use App\Services\Rbac\ScopeResolverRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Rbac\FakePartnerScopeResolver;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * `IMP003-IMPL-M02` — a concrete scope type (one requiring a non-null
 * `scope_id`) must have a registered `ScopeResolver` AND a target that
 * resolver proves exists/is active, or the assignment is REJECTED — never
 * allowed merely because `scope_id != null`.
 */
class ScopeIntegrityTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private int $userSequence = 0;

    private function makePrincipal(): Principal
    {
        $this->userSequence++;
        $user = User::create([
            'email' => "scope-integrity-{$this->userSequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        return app(PrincipalService::class)->forUser($user);
    }

    public function test_concrete_scope_with_no_registered_resolver_is_rejected(): void
    {
        // No CAMPAIGN resolver is registered anywhere in this application yet
        // (only OWN is) — assignment against it must fail closed.
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'scope_integrity_role_1', 'name' => 'Scope Integrity Role 1']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No registered ScopeResolver');

        app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::Campaign, 1);
    }

    public function test_concrete_scope_with_nonexistent_target_is_rejected(): void
    {
        app(ScopeResolverRegistry::class)->register(new FakePartnerScopeResolver([111]));

        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'scope_integrity_role_2', 'name' => 'Scope Integrity Role 2']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exist or is not active');

        app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::Partner, 999);
    }

    public function test_concrete_scope_with_valid_target_succeeds(): void
    {
        app(ScopeResolverRegistry::class)->register(new FakePartnerScopeResolver([111]));

        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'scope_integrity_role_3', 'name' => 'Scope Integrity Role 3']);

        $assignment = app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::Partner, 111);

        $this->assertTrue($assignment->isActive());
        $this->assertSame(111, $assignment->scope_id);
    }

    public function test_concrete_scope_with_inactive_target_is_rejected(): void
    {
        // 222 is deliberately NOT in the active set — simulating a target
        // that once existed but is now inactive/deleted.
        app(ScopeResolverRegistry::class)->register(new FakePartnerScopeResolver([111]));

        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'scope_integrity_role_4', 'name' => 'Scope Integrity Role 4']);

        $this->expectException(\RuntimeException::class);

        app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::Partner, 222);
    }

    public function test_authority_assignment_concrete_scope_with_no_resolver_is_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $authorityType = AuthorityType::firstOrCreate(
            ['code' => 'scope_integrity_authority'],
            ['name' => 'Scope Integrity Authority', 'description' => 'test', 'is_financial' => false],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No registered ScopeResolver');

        app(AuthorityAssignmentService::class)->assign($actor, $target, $authorityType, ScopeType::Fund, 1);
    }

    public function test_global_platform_scope_remains_deterministic_with_no_resolver_required(): void
    {
        // GLOBAL_PLATFORM/ORGANIZATION/OWN require no concrete target row,
        // so no resolver lookup applies to them at all.
        $actor = $this->makeAuthorizedActor();
        $target = $this->makePrincipal();
        $role = Role::create(['code' => 'scope_integrity_role_5', 'name' => 'Scope Integrity Role 5']);

        $assignment = app(RoleAssignmentService::class)->assign($actor, $target, $role, ScopeType::GlobalPlatform, null);

        $this->assertTrue($assignment->isActive());
    }
}
