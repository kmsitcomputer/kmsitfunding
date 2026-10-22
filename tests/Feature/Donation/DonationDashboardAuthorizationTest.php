<?php

namespace Tests\Feature\Donation;

use App\Enums\IdentityLifecycle;
use App\Enums\ScopeType;
use App\Enums\SecurityRestriction;
use App\Models\Donation\Donation;
use App\Models\Donation\DonationRecurringPlan;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Donation\DonationService;
use App\Services\Donation\RecurringPlanService;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-008 Codex final audit FINAL-01 — donor-owned HTTP authorization
 * (docs/implementation/IMP-008-donation.md "Authorization / RBAC").
 *
 * Proves at the HTTP level (never policy-only):
 *
 * - GET /me/donations requires donation.view at OWN scope: a permissionless
 *   principal gets 403; an ORGANIZATION-only grant gets 403 (it cannot
 *   satisfy donor OWN listing under the canonical scope contract); an OWN
 *   grant lists only its own rows; a restricted principal is denied.
 * - GET /me/recurring-plans requires donation.recurring_plan.manage at OWN
 *   scope: permissionless gets 403.
 * - POST /me/recurring-plans requires donation.recurring_plan.manage at OWN
 *   scope: permissionless gets 403, ORGANIZATION-only gets 403, OWN succeeds.
 * - Cross-donor isolation: one donor cannot read another donor's rows.
 *
 * Routes render Inertia pages, so authorized requests assert 200 rather than
 * a payload shape; authorization failures assert 403.
 */
class DonationDashboardAuthorizationTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function actingAsDonor(?User $user = null): array
    {
        $user ??= User::create([
            'email' => 'donor-auth-'.uniqid().'@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        $principal = app(PrincipalService::class)->forUser($user);
        $this->actingAs($user);

        return [$user, $principal];
    }

    private function grant(Principal $principal, array $permissionCodes, ScopeType $scope = ScopeType::Own): void
    {
        $role = Role::create(['code' => 'donor_auth_role_'.uniqid(), 'name' => 'Donor Auth Test Role']);

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

    private function makeOwnDonation(Principal $donor): Donation
    {
        $campaign = $this->makeEligibleCampaign($this->makeUnauthorizedActor());

        return app(DonationService::class)->create($campaign, [
            'amount_minor' => 10000,
            'currency' => 'IDR',
        ], $donor, 'dashauth-'.uniqid());
    }

    private function planPayload(): array
    {
        $campaign = $this->makeEligibleCampaign($this->makeUnauthorizedActor());

        return [
            'campaign_ulid' => $campaign->ulid,
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'frequency' => 'MONTHLY',
        ];
    }

    public function test_donation_list_without_donation_view_is_forbidden(): void
    {
        [, $principal] = $this->actingAsDonor();
        $this->makeOwnDonation($principal);

        $this->get('/me/donations')->assertForbidden();
    }

    public function test_donation_list_with_organization_only_view_grant_is_forbidden(): void
    {
        [, $principal] = $this->actingAsDonor();
        $this->grant($principal, [PermissionRegistry::DONATION_VIEW], ScopeType::Organization);
        $this->makeOwnDonation($principal);

        $this->get('/me/donations')->assertForbidden();
    }

    public function test_donation_list_with_own_view_grant_lists_only_own_donations(): void
    {
        [, $principal] = $this->actingAsDonor();
        $this->grant($principal, [PermissionRegistry::DONATION_VIEW], ScopeType::Own);
        $own = $this->makeOwnDonation($principal);
        $other = $this->makeOwnDonation($this->makeUnauthorizedActor());

        $response = $this->get('/me/donations');

        $response->assertOk();
        $this->assertStringContainsString($own->ulid, $response->getContent());
        $this->assertStringNotContainsString($other->ulid, $response->getContent());
    }

    public function test_donation_list_denies_a_restricted_principal_with_an_otherwise_valid_grant(): void
    {
        [$user, $principal] = $this->actingAsDonor();
        $this->grant($principal, [PermissionRegistry::DONATION_VIEW], ScopeType::Own);
        $this->makeOwnDonation($principal);

        $user->forceFill(['security_restriction' => SecurityRestriction::Suspended])->save();

        $this->get('/me/donations')->assertForbidden();
    }

    public function test_donation_list_denies_a_disabled_identity_with_an_otherwise_valid_grant(): void
    {
        [$user, $principal] = $this->actingAsDonor();
        $this->grant($principal, [PermissionRegistry::DONATION_VIEW], ScopeType::Own);
        $this->makeOwnDonation($principal);

        $user->forceFill(['lifecycle_state' => IdentityLifecycle::Disabled])->save();

        $this->get('/me/donations')->assertForbidden();
    }

    public function test_recurring_plan_list_without_manage_permission_is_forbidden(): void
    {
        $this->actingAsDonor();

        $this->get('/me/recurring-plans')->assertForbidden();
    }

    public function test_recurring_plan_list_with_own_manage_grant_succeeds(): void
    {
        [, $principal] = $this->actingAsDonor();
        $this->grant($principal, [PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE], ScopeType::Own);
        $plan = app(RecurringPlanService::class)->create(
            $this->makeEligibleCampaign($this->makeUnauthorizedActor()),
            ['amount_minor' => 50000, 'currency' => 'IDR', 'frequency' => 'MONTHLY'],
            $principal
        );

        $response = $this->get('/me/recurring-plans');

        $response->assertOk();
        $this->assertStringContainsString($plan->ulid, $response->getContent());
    }

    public function test_recurring_plan_create_without_manage_permission_is_forbidden(): void
    {
        $this->actingAsDonor();

        $this->post('/me/recurring-plans', $this->planPayload())->assertForbidden();
        $this->assertSame(0, DonationRecurringPlan::query()->count());
    }

    public function test_recurring_plan_create_with_organization_only_grant_is_forbidden(): void
    {
        [, $principal] = $this->actingAsDonor();
        $this->grant($principal, [PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE], ScopeType::Organization);

        $this->post('/me/recurring-plans', $this->planPayload())->assertForbidden();
        $this->assertSame(0, DonationRecurringPlan::query()->count());
    }

    public function test_recurring_plan_create_with_own_grant_succeeds(): void
    {
        [, $principal] = $this->actingAsDonor();
        $this->grant($principal, [PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE], ScopeType::Own);

        $this->post('/me/recurring-plans', $this->planPayload())->assertRedirect();

        $plan = DonationRecurringPlan::query()->firstOrFail();
        $this->assertSame($principal->id, $plan->donor_principal_id);
        $this->assertSame('ACTIVE', $plan->status);
    }

    public function test_one_donor_cannot_read_another_donors_donation_or_plan(): void
    {
        [, $donorA] = $this->actingAsDonor();
        $this->grant($donorA, [PermissionRegistry::DONATION_VIEW], ScopeType::Own);
        $donationA = $this->makeOwnDonation($donorA);

        $donorB = $this->makeUnauthorizedActor();
        $donationB = $this->makeOwnDonation($donorB);
        $planB = app(RecurringPlanService::class)->create(
            $this->makeEligibleCampaign($this->makeUnauthorizedActor()),
            ['amount_minor' => 50000, 'currency' => 'IDR', 'frequency' => 'MONTHLY'],
            $donorB
        );

        $this->get("/me/donations/{$donationA->ulid}")->assertOk();
        $this->get("/me/donations/{$donationB->ulid}")->assertForbidden();
        $this->get("/me/recurring-plans/{$planB->ulid}")->assertForbidden();
    }
}
