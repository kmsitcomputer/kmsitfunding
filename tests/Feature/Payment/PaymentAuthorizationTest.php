<?php

namespace Tests\Feature\Payment;

use App\Enums\ScopeType;
use App\Models\Payment\Payment;
use App\Models\Rbac\AuthorityAssignment;
use App\Models\Rbac\AuthorityType;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Policies\PaymentPolicy;
use App\Services\Payment\PaymentCreationService;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Payment\MakesPaymentDonations;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-009 — authorization (docs/implementation/IMP-009-payment-hub.md
 * "Authorization"): every RBAC row — authorized ALLOW, unauthenticated
 * DENY where auth is required, wrong permission DENY, correct
 * permission wrong scope DENY (donor A vs donor B), wrong resource
 * state DENY, missing financial_approver DENY, Super-Admin-name
 * DENY (no role-name shortcut anywhere).
 */
class PaymentAuthorizationTest extends TestCase
{
    use MakesPaymentDonations;
    use RbacTestActors;
    use RefreshDatabase;

    private function grant(Principal $principal, array $permissionCodes, ScopeType $scope = ScopeType::Own): void
    {
        $role = Role::create(['code' => 'pay_auth_'.uniqid(), 'name' => 'Payment Auth Test Role']);

        foreach ($permissionCodes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['description' => 'test']);
            $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        }

        PrincipalRoleAssignment::create([
            'principal_id' => $principal->id,
            'role_id' => $role->id,
            'scope_type' => $scope->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);
    }

    private function grantAuthority(Principal $target, Principal $granter): void
    {
        $authorityType = AuthorityType::firstOrCreate(
            ['code' => AuthorityType::FINANCIAL_APPROVER],
            ['name' => 'Financial Approver', 'description' => 'test', 'is_financial' => true],
        );

        AuthorityAssignment::create([
            'principal_id' => $target->id,
            'authority_type_id' => $authorityType->id,
            'scope_type' => ScopeType::Organization->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => $granter->id,
        ]);
    }

    private function ownPayment(Principal $owner): Payment
    {
        $donation = $this->makePendingOwnedDonation($owner);

        return app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], $owner, 'auth-'.uniqid()
        );
    }

    public function test_donor_views_own_payment_with_permission_and_own_scope(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $payment = $this->ownPayment($owner);
        $this->grant($owner, [PermissionRegistry::PAYMENT_VIEW], ScopeType::Own);

        $this->assertTrue(app(PaymentPolicy::class)->viewOwn($owner, $payment));
    }

    public function test_donor_cannot_view_another_donors_payment(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $intruder = $this->makeUnauthorizedActor();
        $payment = $this->ownPayment($owner);
        $this->grant($intruder, [PermissionRegistry::PAYMENT_VIEW], ScopeType::Own);

        $this->assertFalse(app(PaymentPolicy::class)->viewOwn($intruder, $payment));
    }

    public function test_view_without_the_permission_is_denied(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $payment = $this->ownPayment($owner);

        $this->assertFalse(app(PaymentPolicy::class)->viewOwn($owner, $payment));
    }

    public function test_admin_views_any_payment_at_organization_scope(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $admin = $this->makeUnauthorizedActor();
        $payment = $this->ownPayment($owner);
        $this->grant($admin, [PermissionRegistry::PAYMENT_VIEW], ScopeType::Organization);

        $this->assertTrue(app(PaymentPolicy::class)->viewPayment($admin, $payment));
        $this->assertTrue(app(PaymentPolicy::class)->viewAny($admin));
    }

    public function test_own_scoped_grant_never_authorizes_cross_donor_admin_read(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $admin = $this->makeUnauthorizedActor();
        $payment = $this->ownPayment($owner);
        $this->grant($admin, [PermissionRegistry::PAYMENT_VIEW], ScopeType::Own);

        $this->assertFalse(app(PaymentPolicy::class)->viewPayment($admin, $payment));
        $this->assertFalse(app(PaymentPolicy::class)->viewAny($admin));
    }

    public function test_own_list_uses_own_scope_semantics(): void
    {
        $policy = app(PaymentPolicy::class);

        $owner = $this->makeUnauthorizedActor();
        $this->ownPayment($owner);
        $this->grant($owner, [PermissionRegistry::PAYMENT_VIEW], ScopeType::Own);
        $this->assertTrue($policy->viewOwnList($owner));

        $ungranted = $this->makeUnauthorizedActor();
        $this->assertFalse($policy->viewOwnList($ungranted));

        $orgOnly = $this->makeUnauthorizedActor();
        $this->grant($orgOnly, [PermissionRegistry::PAYMENT_VIEW], ScopeType::Organization);
        $this->assertFalse($policy->viewOwnList($orgOnly));

        $platform = $this->makeUnauthorizedActor();
        $this->grant($platform, [PermissionRegistry::PAYMENT_VIEW], ScopeType::GlobalPlatform);
        $this->assertTrue($policy->viewOwnList($platform));
    }

    public function test_donor_cancels_own_pending_payment(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $payment = $this->ownPayment($owner);
        $this->grant($owner, [PermissionRegistry::PAYMENT_CANCEL], ScopeType::Own);

        $this->assertTrue(app(PaymentPolicy::class)->cancelOwn($owner, $payment));
    }

    public function test_cancel_in_wrong_resource_state_is_denied(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $payment = $this->ownPayment($owner);
        $payment->forceFill(['status' => 'SUCCEEDED'])->save();
        $this->grant($owner, [PermissionRegistry::PAYMENT_CANCEL], ScopeType::Own);

        $this->assertFalse(app(PaymentPolicy::class)->cancelOwn($owner, $payment));
    }

    public function test_manual_verify_requires_permission_scope_authority_and_pending_state(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $payment = $this->ownPayment($owner);
        $policy = app(PaymentPolicy::class);

        $noPermission = $this->makeUnauthorizedActor();
        $this->assertFalse($policy->verifyManualTransfer($noPermission, $payment));

        $noAuthority = $this->makeUnauthorizedActor();
        $this->grant($noAuthority, [PermissionRegistry::PAYMENT_MANUAL_TRANSFER_VERIFY], ScopeType::Organization);
        $this->assertFalse($policy->verifyManualTransfer($noAuthority, $payment));

        $verifier = $this->makeUnauthorizedActor();
        $this->grant($verifier, [PermissionRegistry::PAYMENT_MANUAL_TRANSFER_VERIFY], ScopeType::Organization);
        $this->grantAuthority($verifier, $this->makeAuthorizedActor());
        $this->assertTrue($policy->verifyManualTransfer($verifier, $payment));

        $payment->forceFill(['status' => 'FAILED'])->save();
        $this->assertFalse($policy->verifyManualTransfer($verifier, $payment));
    }

    public function test_provider_config_requires_global_platform_scope(): void
    {
        $policy = app(PaymentPolicy::class);

        $orgAdmin = $this->makeUnauthorizedActor();
        $this->grant($orgAdmin, [PermissionRegistry::PAYMENT_PROVIDER_CONFIG_MANAGE], ScopeType::Organization);
        $this->assertFalse($policy->manageProviderConfig($orgAdmin));

        $platformAdmin = $this->makeUnauthorizedActor();
        $this->grant($platformAdmin, [PermissionRegistry::PAYMENT_PROVIDER_CONFIG_MANAGE], ScopeType::GlobalPlatform);
        $this->assertTrue($policy->manageProviderConfig($platformAdmin));
    }

    public function test_system_transitions_are_never_reachable_through_a_human_principal(): void
    {
        $owner = $this->makeUnauthorizedActor();
        $payment = $this->ownPayment($owner);
        $policy = app(PaymentPolicy::class);

        $this->grant($owner, [
            PermissionRegistry::PAYMENT_VIEW,
            PermissionRegistry::PAYMENT_CREATE,
            PermissionRegistry::PAYMENT_CANCEL,
            PermissionRegistry::PAYMENT_MANUAL_TRANSFER_VERIFY,
            PermissionRegistry::PAYMENT_PROVIDER_CONFIG_MANAGE,
        ], ScopeType::GlobalPlatform);

        $this->assertFalse($policy->transitionAsSystem($owner, $payment));
    }

    public function test_no_role_name_shortcut_exists_in_the_payment_policy(): void
    {
        $code = file_get_contents(app_path('Policies/PaymentPolicy.php'));

        $this->assertDoesNotMatchRegularExpression(
            '/role\s*===?\s*[\'"]super_admin[\'"]/i',
            $code,
            'PaymentPolicy must never branch on a role name.'
        );
    }
}
