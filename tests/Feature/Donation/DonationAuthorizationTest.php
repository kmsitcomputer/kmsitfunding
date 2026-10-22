<?php

namespace Tests\Feature\Donation;

use App\Enums\ScopeType;
use App\Models\Donation\Donation;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Policies\DonationPolicy;
use App\Policies\RecurringPlanPolicy;
use App\Services\Donation\DonationService;
use App\Services\Donation\RecurringPlanService;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-008 — RBAC matrix (docs/implementation/IMP-008-donation.md
 * "Authorization / RBAC", AC-008-008/009/015/016/022/023/026). Default
 * DENY; cross-donor denial; wrong-scope denial; wrong-state denial;
 * no guest self-service cancellation; admin override.
 */
class DonationAuthorizationTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;
    use RefreshDatabase;

    private function makeGrantedActor(array $permissionCodes, ScopeType $scope = ScopeType::GlobalPlatform): Principal
    {
        $actor = $this->makeUnauthorizedActor();
        $role = Role::create(['code' => 'donation_test_role_'.uniqid(), 'name' => 'Donation Test Role']);

        foreach ($permissionCodes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['description' => 'test']);
            $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        }

        PrincipalRoleAssignment::create([
            'principal_id' => $actor->id,
            'role_id' => $role->id,
            'scope_type' => $scope->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);

        return $actor;
    }

    private function makeOwnDonation(Principal $donor): Donation
    {
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        return app(DonationService::class)->create($campaign, [
            'amount_minor' => 10000,
            'currency' => 'IDR',
        ], $donor, 'own-'.uniqid());
    }

    public function test_donor_views_only_their_own_donations(): void
    {
        $donorA = $this->makeGrantedActor([PermissionRegistry::DONATION_VIEW]);
        $donorB = $this->makeGrantedActor([PermissionRegistry::DONATION_VIEW]);
        $policy = new DonationPolicy;

        $ownA = $this->makeOwnDonation($donorA);
        $ownB = $this->makeOwnDonation($donorB);

        $this->assertTrue($policy->viewOwn($donorA, $ownA));
        $this->assertFalse($policy->viewOwn($donorA, $ownB));
        $this->assertTrue($policy->viewOwn($donorB, $ownB));
        $this->assertFalse($policy->viewOwn($donorB, $ownA));
    }

    public function test_donor_cannot_cancel_another_donors_donation(): void
    {
        $donorA = $this->makeGrantedActor([PermissionRegistry::DONATION_CANCEL]);
        $donorB = $this->makeGrantedActor([PermissionRegistry::DONATION_CANCEL]);
        $policy = new DonationPolicy;

        $ownA = $this->makeOwnDonation($donorA);

        $this->assertTrue($policy->cancelOwn($donorA, $ownA));
        $this->assertFalse($policy->cancelOwn($donorB, $ownA));
        $this->assertSame('PENDING', $ownA->fresh()->status);
    }

    public function test_cancel_requires_pending_state_even_for_the_owner(): void
    {
        $donor = $this->makeGrantedActor([PermissionRegistry::DONATION_CANCEL]);
        $policy = new DonationPolicy;
        $donation = $this->makeOwnDonation($donor);
        $donation->forceFill(['status' => 'SUCCEEDED'])->save();

        $this->assertFalse($policy->cancelOwn($donor, $donation->fresh()));
    }

    public function test_cancel_requires_the_permission(): void
    {
        $withPermission = $this->makeGrantedActor([PermissionRegistry::DONATION_CANCEL]);
        $withoutPermission = $this->makeUnauthorizedActor();
        $policy = new DonationPolicy;

        $donation = $this->makeOwnDonation($withPermission);

        $this->assertTrue($policy->cancelOwn($withPermission, $donation));

        $other = $this->makeOwnDonation($withoutPermission);

        $this->assertFalse($policy->cancelOwn($withoutPermission, $other));
    }

    public function test_guest_donation_never_satisfies_own_scope_ownership(): void
    {
        $actor = $this->makeGrantedActor([PermissionRegistry::DONATION_VIEW, PermissionRegistry::DONATION_CANCEL]);
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        $guest = app(DonationService::class)->create($campaign, [
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'guest_name' => 'G',
            'guest_email' => 'g@example.com',
        ], null, 'guestown-'.uniqid());

        $policy = new DonationPolicy;

        $this->assertFalse($policy->viewOwn($actor, $guest));
        $this->assertFalse($policy->cancelOwn($actor, $guest));
    }

    public function test_admin_views_and_cancels_across_donors_at_organization_scope(): void
    {
        $admin = $this->makeGrantedActor(
            [PermissionRegistry::DONATION_VIEW, PermissionRegistry::DONATION_CANCEL],
            ScopeType::Organization
        );
        $donor = $this->makeGrantedActor([PermissionRegistry::DONATION_CANCEL], ScopeType::Own);
        $policy = new DonationPolicy;

        $donation = $this->makeOwnDonation($donor);

        $this->assertTrue($policy->viewDonation($admin, $donation));
        $this->assertTrue($policy->cancelAny($admin, $donation));
        $this->assertFalse($policy->cancelAny($donor, $donation));
    }

    public function test_own_scoped_grant_does_not_cover_organization_actions(): void
    {
        $ownOnly = $this->makeGrantedActor([PermissionRegistry::DONATION_CANCEL], ScopeType::Own);
        $donor = $this->makeGrantedActor([PermissionRegistry::DONATION_CANCEL]);
        $policy = new DonationPolicy;

        $donation = $this->makeOwnDonation($donor);

        $this->assertFalse($policy->cancelAny($ownOnly, $donation));
    }

    public function test_own_scoped_view_grant_cannot_access_the_admin_donation_list(): void
    {
        $ownOnly = $this->makeGrantedActor([PermissionRegistry::DONATION_VIEW], ScopeType::Own);
        $policy = new DonationPolicy;

        $this->assertFalse($policy->viewAny($ownOnly));
    }

    public function test_own_scoped_plan_grant_cannot_view_another_donors_admin_plan(): void
    {
        $ownOnly = $this->makeGrantedActor([PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE], ScopeType::Own);
        $policy = new RecurringPlanPolicy;

        $this->assertFalse($policy->viewAny($ownOnly));
    }

    public function test_wrong_organization_permission_does_not_authorize_the_admin_list(): void
    {
        $wrongPermission = $this->makeGrantedActor([PermissionRegistry::DONATION_CANCEL], ScopeType::Organization);
        $policy = new DonationPolicy;

        $this->assertFalse($policy->viewAny($wrongPermission));
    }

    public function test_organization_scoped_view_grant_authorizes_the_admin_list_and_plan_view(): void
    {
        $admin = $this->makeGrantedActor(
            [PermissionRegistry::DONATION_VIEW, PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE],
            ScopeType::Organization
        );

        $this->assertTrue((new DonationPolicy)->viewAny($admin));
        $this->assertTrue((new RecurringPlanPolicy)->viewAny($admin));
    }

    public function test_human_principal_cannot_invoke_the_system_consequence_surface(): void
    {
        $human = $this->makeAuthorizedActor();
        $donation = $this->makeOwnDonation($this->makeUnauthorizedActor());
        $policy = new DonationPolicy;

        $this->assertFalse($policy->markSucceeded($human, $donation));
        $this->assertFalse($policy->markFailed($human, $donation));
        $this->assertFalse($policy->markExpired($human, $donation));
    }

    public function test_recurring_plan_cross_donor_denial_and_admin_override(): void
    {
        $donorA = $this->makeGrantedActor([PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE], ScopeType::Own);
        $donorB = $this->makeGrantedActor([PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE], ScopeType::Own);
        $admin = $this->makeGrantedActor(
            [PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE],
            ScopeType::Organization
        );
        $policy = new RecurringPlanPolicy;

        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);
        $plan = app(RecurringPlanService::class)->create($campaign, [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'frequency' => 'MONTHLY',
        ], $donorA);

        $this->assertTrue($policy->pauseOwn($donorA, $plan));
        $this->assertFalse($policy->pauseOwn($donorB, $plan));
        $this->assertTrue($policy->pauseAny($admin, $plan));
        $this->assertFalse($policy->pauseAny($donorA, $plan));

        $this->assertFalse($policy->resumeOwn($donorA, $plan));
        $this->assertTrue($policy->cancelOwn($donorA, $plan));
        $this->assertTrue($policy->cancelAny($admin, $plan));
    }
}
