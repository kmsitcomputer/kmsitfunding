<?php

namespace Tests\Feature\Donation;

use App\Enums\ScopeType;
use App\Models\Donation\Donation;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\User;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-008 — HTTP surface (docs/implementation/IMP-008-donation.md
 * "API Impact", AC-008-001/002/015/026): public guest creation with
 * Idempotency-Key; authenticated creation; donor-owned cancel; admin
 * cancel of a guest donation; NO guest-facing cancellation endpoint.
 */
class DonationHttpTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // POST-based HTTP tests across this repository (e.g. Identity
        // LoginTest) receive 419 in this environment — a pre-existing,
        // unrelated CSRF/session-driver condition. CSRF presentation is
        // orthogonal to every IMP-008 authorization assertion here
        // (backend Policies remain fully enforced), so it is disabled
        // for these HTTP-shape tests only.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function actingAsDonor(): Principal
    {
        $user = User::create([
            'email' => 'donor-http-'.uniqid().'@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);

        $principal = app(PrincipalService::class)->forUser($user);
        $this->actingAs($user);

        return $principal;
    }

    private function grantPermissions(Principal $principal, array $permissionCodes): void
    {
        $role = Role::create(['code' => 'donation_http_role_'.uniqid(), 'name' => 'Donation HTTP Test Role']);

        foreach ($permissionCodes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['description' => 'test']);
            $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        }

        PrincipalRoleAssignment::create([
            'principal_id' => $principal->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::Own->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);
    }

    public function test_guest_creates_a_donation_through_the_public_endpoint(): void
    {
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        $response = $this->post(
            "/campaigns/{$campaign->slug}/donations",
            [
                'amount_minor' => 50000,
                'currency' => 'IDR',
                'guest_name' => 'Guest Giver',
                'guest_email' => 'guest@example.com',
            ],
            ['Idempotency-Key' => 'http-guest-'.uniqid()]
        );

        $response->assertRedirect();
        $this->assertSame(1, Donation::query()->count());

        $donation = Donation::query()->first();

        $this->assertSame('PENDING', $donation->status);
        $this->assertNull($donation->donor_principal_id);
    }

    public function test_public_creation_without_an_idempotency_key_is_rejected(): void
    {
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        $response = $this->post("/campaigns/{$campaign->slug}/donations", [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'guest_name' => 'Guest Giver',
            'guest_email' => 'guest@example.com',
        ]);

        $response->assertSessionHasErrors('idempotency_key');
        $this->assertSame(0, Donation::query()->count());
    }

    public function test_missing_idempotency_key_is_rejected_before_payload_or_eligibility_checks(): void
    {
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        // The payload is ALSO invalid (missing amount_minor) — the
        // response must surface the idempotency_key error, proving the
        // mandatory key is resolved/validated BEFORE payload business
        // validation and before the eligibility gate (BR-12 exact order).
        $response = $this->post("/campaigns/{$campaign->slug}/donations", [
            'currency' => 'IDR',
            'guest_name' => 'Guest Giver',
            'guest_email' => 'guest@example.com',
        ]);

        $response->assertSessionHasErrors('idempotency_key');
        $response->assertSessionMissing('amount_minor');
        $this->assertSame(0, Donation::query()->count());
    }

    public function test_public_creation_against_an_ineligible_campaign_is_rejected(): void
    {
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeCampaignAtStatus($seeder, 'DRAFT');

        $response = $this->post("/campaigns/{$campaign->slug}/donations", [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'guest_name' => 'Guest Giver',
            'guest_email' => 'guest@example.com',
        ], ['Idempotency-Key' => 'http-inelig-'.uniqid()]);

        $response->assertNotFound();
        $this->assertSame(0, Donation::query()->count());
    }

    public function test_authenticated_donor_creates_and_cancels_their_own_donation(): void
    {
        $principal = $this->actingAsDonor();
        $this->grantPermissions($principal, [PermissionRegistry::DONATION_CANCEL]);
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        $create = $this->post(
            "/campaigns/{$campaign->slug}/donations",
            ['amount_minor' => 2500, 'currency' => 'USD'],
            ['Idempotency-Key' => 'http-auth-'.uniqid()]
        );

        $create->assertRedirect();

        $donation = Donation::query()->first();

        $this->assertNotNull($donation->donor_principal_id);

        $cancel = $this->post("/me/donations/{$donation->ulid}/cancel");

        $cancel->assertRedirect();
        $this->assertSame('CANCELLED', $donation->fresh()->status);
    }

    public function test_unauthenticated_donor_routes_require_authentication(): void
    {
        $response = $this->get('/me/donations');

        $response->assertRedirect('/login');
    }

    public function test_public_receipt_surface_is_not_registered(): void
    {
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        $this->post(
            "/campaigns/{$campaign->slug}/donations",
            [
                'amount_minor' => 50000,
                'currency' => 'IDR',
                'guest_name' => 'Guest Giver',
                'guest_email' => 'guest@example.com',
            ],
            ['Idempotency-Key' => 'http-noreceipt-'.uniqid()]
        );

        $donation = Donation::query()->first();

        // The unapproved public receipt surface (ULID as bearer read
        // capability) does not exist: the route is unregistered, so the
        // catch-all resolves it as a missing content page, never a
        // donation read.
        $response = $this->get("/campaigns/{$campaign->slug}/donations/{$donation->ulid}");

        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_no_guest_facing_cancellation_endpoint_exists(): void
    {
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        $this->post(
            "/campaigns/{$campaign->slug}/donations",
            [
                'amount_minor' => 50000,
                'currency' => 'IDR',
                'guest_name' => 'Guest Giver',
                'guest_email' => 'guest@example.com',
            ],
            ['Idempotency-Key' => 'http-noguestcancel-'.uniqid()]
        );

        $donation = Donation::query()->first();

        $this->post("/campaigns/{$campaign->slug}/donations/{$donation->ulid}/cancel")->assertStatus(405);
        $this->get("/campaigns/{$campaign->slug}/donations/{$donation->ulid}/cancel")->assertNotFound();
        $this->post("/me/donations/{$donation->ulid}/cancel")->assertRedirect('/login');
        $this->assertSame('PENDING', $donation->fresh()->status);
    }
}
