<?php

namespace Tests\Feature\Rbac;

use App\Enums\IdentityLifecycle;
use App\Enums\PrincipalKind;
use App\Enums\ScopeType;
use App\Enums\SecurityRestriction;
use App\Models\Rbac\AuthorityType;
use App\Models\Rbac\IntegrationPrincipal;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\Role;
use App\Models\Rbac\SystemPrincipal;
use App\Models\User;
use App\Services\Identity\AssuranceService;
use App\Services\Rbac\AuthorizationEvaluator;
use App\Services\Rbac\AuthorizationRequest;
use App\Services\Rbac\OwnUserScopeResolver;
use App\Services\Rbac\PrincipalService;
use App\Services\Rbac\RoleAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Rbac\FakePartnerResource;
use Tests\Support\Rbac\FakePartnerScopeResolver;
use Tests\TestCase;

class AuthorizationEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private int $userSequence = 0;

    private function makeUser(array $overrides = []): User
    {
        $this->userSequence++;
        $user = User::create([
            'email' => "evaluator-test-{$this->userSequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        if ($overrides !== []) {
            $user->forceFill($overrides)->save();
        }

        return $user;
    }

    private function permission(string $code): Permission
    {
        return Permission::firstOrCreate(['code' => $code], ['description' => 'test']);
    }

    private function grantRole(Principal $grantor, Principal $target, Role $role, ScopeType $scopeType = ScopeType::GlobalPlatform, ?int $scopeId = null): void
    {
        app(RoleAssignmentService::class)->assign($grantor, $target, $role, $scopeType, $scopeId);
    }

    public function test_missing_permission_is_denied(): void
    {
        $principal = app(PrincipalService::class)->forUser($this->makeUser());
        $this->permission('rbac.test.action');

        $result = app(AuthorizationEvaluator::class)->evaluate(new AuthorizationRequest(
            principal: $principal,
            permissionCode: 'rbac.test.action',
        ));

        $this->assertFalse($result);
    }

    public function test_unknown_permission_code_is_denied(): void
    {
        $principal = app(PrincipalService::class)->forUser($this->makeUser());

        $result = app(AuthorizationEvaluator::class)->evaluate(new AuthorizationRequest(
            principal: $principal,
            permissionCode: 'rbac.nonexistent.permission',
        ));

        $this->assertFalse($result);
    }

    public function test_granted_permission_with_no_scope_requirement_is_allowed(): void
    {
        $admin = app(PrincipalService::class)->forUser($this->makeUser());
        $target = app(PrincipalService::class)->forUser($this->makeUser());
        $permission = $this->permission('rbac.test.no_scope');
        $role = Role::create(['code' => 'eval_role_2', 'name' => 'Eval Role 2']);
        $role->permissions()->attach($permission->id, ['granted_at' => now()]);
        $this->grantRole($admin, $target, $role);

        $result = app(AuthorizationEvaluator::class)->evaluate(new AuthorizationRequest(
            principal: $target,
            permissionCode: 'rbac.test.no_scope',
        ));

        $this->assertTrue($result);
    }

    public function test_donor_cross_ownership_is_denied(): void
    {
        $admin = app(PrincipalService::class)->forUser($this->makeUser());
        $donorAUser = $this->makeUser();
        $donorBUser = $this->makeUser();
        $donorA = app(PrincipalService::class)->forUser($donorAUser);
        app(PrincipalService::class)->forUser($donorBUser);

        $permission = $this->permission('rbac.test.own_scope');
        $role = Role::create(['code' => 'eval_role_own', 'name' => 'Eval Role Own']);
        $role->permissions()->attach($permission->id, ['granted_at' => now()]);
        $this->grantRole($admin, $donorA, $role, ScopeType::Own, null);

        $evaluator = app(AuthorizationEvaluator::class);
        $resolver = new OwnUserScopeResolver;

        $allowOwn = $evaluator->evaluate(new AuthorizationRequest(
            principal: $donorA,
            permissionCode: 'rbac.test.own_scope',
            resource: $donorAUser,
            scopeResolver: $resolver,
        ));
        $this->assertTrue($allowOwn, 'Donor A must be allowed against Donor A\'s own resource.');

        $denyCrossOwner = $evaluator->evaluate(new AuthorizationRequest(
            principal: $donorA,
            permissionCode: 'rbac.test.own_scope',
            resource: $donorBUser,
            scopeResolver: $resolver,
        ));
        $this->assertFalse($denyCrossOwner, 'Donor A must be denied against Donor B\'s private resource.');
    }

    public function test_partner_representative_cross_scope_is_denied_no_tenancy_introduced(): void
    {
        $admin = app(PrincipalService::class)->forUser($this->makeUser());
        $partnerRepA = app(PrincipalService::class)->forUser($this->makeUser());

        $permission = $this->permission('rbac.test.partner_scope');
        $role = Role::create(['code' => 'partner_representative_test', 'name' => 'Partner Representative']);
        $role->permissions()->attach($permission->id, ['granted_at' => now()]);
        $this->grantRole($admin, $partnerRepA, $role, ScopeType::Partner, 111);

        $evaluator = app(AuthorizationEvaluator::class);
        $resolver = new FakePartnerScopeResolver;

        $allowOwnPartner = $evaluator->evaluate(new AuthorizationRequest(
            principal: $partnerRepA,
            permissionCode: 'rbac.test.partner_scope',
            resource: new FakePartnerResource(111),
            scopeResolver: $resolver,
        ));
        $this->assertTrue($allowOwnPartner);

        $denyOtherPartner = $evaluator->evaluate(new AuthorizationRequest(
            principal: $partnerRepA,
            permissionCode: 'rbac.test.partner_scope',
            resource: new FakePartnerResource(222),
            scopeResolver: $resolver,
        ));
        $this->assertFalse($denyOtherPartner, 'Partner Representative A must never access Partner B\'s resource.');
    }

    public function test_security_restriction_disabled_denies_even_with_valid_permission(): void
    {
        $admin = app(PrincipalService::class)->forUser($this->makeUser());
        $targetUser = $this->makeUser();
        $target = app(PrincipalService::class)->forUser($targetUser);

        $permission = $this->permission('rbac.test.disabled');
        $role = Role::create(['code' => 'eval_role_disabled', 'name' => 'Eval Role Disabled']);
        $role->permissions()->attach($permission->id, ['granted_at' => now()]);
        $this->grantRole($admin, $target, $role);

        // Grant while ACTIVE, then transition to DISABLED — DISABLED must
        // deny even though the Role assignment remains active/unrevoked.
        $targetUser->forceFill(['lifecycle_state' => IdentityLifecycle::Disabled])->save();

        $result = app(AuthorizationEvaluator::class)->evaluate(new AuthorizationRequest(
            principal: $target,
            permissionCode: 'rbac.test.disabled',
        ));

        $this->assertFalse($result);
    }

    public function test_security_restriction_suspended_denies_even_with_valid_permission(): void
    {
        $admin = app(PrincipalService::class)->forUser($this->makeUser());
        $targetUser = $this->makeUser();
        $target = app(PrincipalService::class)->forUser($targetUser);

        $permission = $this->permission('rbac.test.suspended');
        $role = Role::create(['code' => 'eval_role_suspended', 'name' => 'Eval Role Suspended']);
        $role->permissions()->attach($permission->id, ['granted_at' => now()]);
        $this->grantRole($admin, $target, $role);

        $targetUser->forceFill(['security_restriction' => SecurityRestriction::Suspended])->save();

        $result = app(AuthorizationEvaluator::class)->evaluate(new AuthorizationRequest(
            principal: $target,
            permissionCode: 'rbac.test.suspended',
        ));

        $this->assertFalse($result);
    }

    public function test_elevated_assurance_required_denies_standard_session(): void
    {
        $admin = app(PrincipalService::class)->forUser($this->makeUser());
        $target = app(PrincipalService::class)->forUser($this->makeUser());

        $permission = $this->permission('rbac.test.elevated');
        $role = Role::create(['code' => 'eval_role_elevated', 'name' => 'Eval Role Elevated']);
        $role->permissions()->attach($permission->id, ['granted_at' => now()]);
        $this->grantRole($admin, $target, $role);

        $result = app(AuthorizationEvaluator::class)->evaluate(new AuthorizationRequest(
            principal: $target,
            permissionCode: 'rbac.test.elevated',
            requiresElevatedAssurance: true,
        ));

        $this->assertFalse($result, 'STANDARD assurance must not satisfy an ELEVATED requirement.');

        app(AssuranceService::class)->elevate();

        $resultElevated = app(AuthorizationEvaluator::class)->evaluate(new AuthorizationRequest(
            principal: $target,
            permissionCode: 'rbac.test.elevated',
            requiresElevatedAssurance: true,
        ));

        $this->assertTrue($resultElevated);
    }

    public function test_super_admin_without_financial_authority_is_denied_on_financial_action(): void
    {
        $admin = app(PrincipalService::class)->forUser($this->makeUser());
        $superAdminTarget = app(PrincipalService::class)->forUser($this->makeUser());

        $permission = $this->permission('finance.refund.approve');
        $role = Role::create(['code' => 'super_admin_eval_test', 'name' => 'Super Admin']);
        $role->permissions()->attach($permission->id, ['granted_at' => now()]);
        $this->grantRole($admin, $superAdminTarget, $role);

        $financialApprover = AuthorityType::firstOrCreate(
            ['code' => AuthorityType::FINANCIAL_APPROVER],
            ['name' => 'Financial Approver', 'description' => 'test', 'is_financial' => true],
        );

        $result = app(AuthorizationEvaluator::class)->evaluate(new AuthorizationRequest(
            principal: $superAdminTarget,
            permissionCode: 'finance.refund.approve',
            requiredAuthorityType: $financialApprover,
            requestedScopeType: ScopeType::GlobalPlatform,
        ));

        $this->assertFalse($result, 'Super Admin role/permission alone must never satisfy a required Business/Financial Authority.');
    }

    public function test_fundraiser_without_business_authority_is_denied(): void
    {
        $admin = app(PrincipalService::class)->forUser($this->makeUser());
        $fundraiser = app(PrincipalService::class)->forUser($this->makeUser());

        $permission = $this->permission('campaign.publish');
        $role = Role::create(['code' => 'fundraiser_eval_test', 'name' => 'Fundraiser']);
        $role->permissions()->attach($permission->id, ['granted_at' => now()]);
        $this->grantRole($admin, $fundraiser, $role, ScopeType::Fundraiser, 5);

        $zakatAuthority = AuthorityType::firstOrCreate(
            ['code' => AuthorityType::ZAKAT_AUTHORITY],
            ['name' => 'Zakat Authority', 'description' => 'test', 'is_financial' => false],
        );

        $result = app(AuthorizationEvaluator::class)->evaluate(new AuthorizationRequest(
            principal: $fundraiser,
            permissionCode: 'campaign.publish',
            requiredAuthorityType: $zakatAuthority,
            requestedScopeType: ScopeType::Fundraiser,
            requestedScopeId: 5,
        ));

        $this->assertFalse($result);
    }

    public function test_invalid_principal_denies_with_no_fallback(): void
    {
        $unsavedPrincipal = new Principal(['principal_kind' => PrincipalKind::Human]);

        $result = app(AuthorizationEvaluator::class)->evaluate(new AuthorizationRequest(
            principal: $unsavedPrincipal,
            permissionCode: 'rbac.role.assign',
        ));

        $this->assertFalse($result);
    }

    public function test_missing_scope_target_resource_denies(): void
    {
        $admin = app(PrincipalService::class)->forUser($this->makeUser());
        $target = app(PrincipalService::class)->forUser($this->makeUser());

        $permission = $this->permission('rbac.test.missing_target');
        $role = Role::create(['code' => 'eval_role_missing_target', 'name' => 'Eval Role Missing Target']);
        $role->permissions()->attach($permission->id, ['granted_at' => now()]);
        $this->grantRole($admin, $target, $role, ScopeType::Partner, 1);

        $result = app(AuthorizationEvaluator::class)->evaluate(new AuthorizationRequest(
            principal: $target,
            permissionCode: 'rbac.test.missing_target',
            resource: null,
            scopeResolver: new FakePartnerScopeResolver,
        ));

        $this->assertFalse($result, 'A null Target Resource with a scope resolver supplied must deny at Step 0.');
    }

    public function test_system_principal_overreach_is_denied(): void
    {
        $systemPrincipalRow = SystemPrincipal::create(['code' => 'test_scheduler']);
        $systemPrincipal = app(PrincipalService::class)->forSystem($systemPrincipalRow);

        // Deliberately grant NOTHING to this System Principal — it must never
        // have implicit "god mode" capability.
        $result = app(AuthorizationEvaluator::class)->evaluate(new AuthorizationRequest(
            principal: $systemPrincipal,
            permissionCode: 'finance.refund.approve',
        ));

        $this->assertFalse($result);
    }

    public function test_integration_principal_overreach_is_denied(): void
    {
        $integrationPrincipalRow = IntegrationPrincipal::create(['code' => 'test_payment_webhook']);
        $integrationPrincipal = app(PrincipalService::class)->forIntegration($integrationPrincipalRow);

        $admin = app(PrincipalService::class)->forUser($this->makeUser());
        $narrowPermission = $this->permission('payment.webhook.receive');
        $role = Role::create(['code' => 'integration_test_role', 'name' => 'Integration Webhook']);
        $role->permissions()->attach($narrowPermission->id, ['granted_at' => now()]);
        $this->grantRole($admin, $integrationPrincipal, $role);

        // Granted only payment.webhook.receive — attempting an unrelated capability must deny.
        $result = app(AuthorizationEvaluator::class)->evaluate(new AuthorizationRequest(
            principal: $integrationPrincipal,
            permissionCode: 'finance.refund.approve',
        ));

        $this->assertFalse($result);
    }

    public function test_disabled_system_principal_cannot_authorize(): void
    {
        $systemPrincipalRow = SystemPrincipal::create(['code' => 'disabled_system', 'deactivated_at' => now()]);
        $systemPrincipal = app(PrincipalService::class)->forSystem($systemPrincipalRow);

        $this->assertFalse($systemPrincipal->canAuthorize());
    }
}
