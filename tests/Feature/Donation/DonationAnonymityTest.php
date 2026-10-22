<?php

namespace Tests\Feature\Donation;

use App\Http\Controllers\PublicDonationController;
use App\Models\Donation\Donation;
use App\Services\Donation\DonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-008 — public anonymity contract (Q6, BR-4, AC-008-012/013):
 * is_anonymous=true never leaks donor_display_name/guest_name/donor
 * identity through the PUBLIC read path, while organization-scoped
 * admin visibility is unaffected.
 */
class DonationAnonymityTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;
    use RefreshDatabase;

    public function test_anonymous_public_payload_omits_every_donor_identifier(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        $donation = app(DonationService::class)->create($campaign, [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'guest_name' => 'Secret Giver',
            'guest_email' => 'secret@example.com',
            'donor_display_name' => 'Secret Giver',
            'is_anonymous' => true,
        ], null, 'anon-'.uniqid());

        $payload = PublicDonationController::publicPayload($donation->fresh());

        $this->assertNull($payload['donor_display_name']);
        $this->assertArrayNotHasKey('guest_name', $payload);
        $this->assertArrayNotHasKey('guest_email', $payload);
        $this->assertArrayNotHasKey('donor_principal_id', $payload);
        $this->assertSame(50000, $payload['amount_minor']);
    }

    public function test_non_anonymous_public_payload_includes_the_display_name(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        $donation = app(DonationService::class)->create($campaign, [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'guest_name' => 'Open Giver',
            'guest_email' => 'open@example.com',
            'donor_display_name' => 'Open Giver',
            'is_anonymous' => false,
        ], null, 'open-'.uniqid());

        $payload = PublicDonationController::publicPayload($donation->fresh());

        $this->assertSame('Open Giver', $payload['donor_display_name']);
        $this->assertArrayNotHasKey('guest_name', $payload);
        $this->assertArrayNotHasKey('guest_email', $payload);
    }

    public function test_internal_identity_survives_anonymity_for_admin_and_audit(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        $donation = app(DonationService::class)->create($campaign, [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'guest_name' => 'Secret Giver',
            'guest_email' => 'secret@example.com',
            'donor_display_name' => 'Secret Giver',
            'is_anonymous' => true,
        ], null, 'internal-'.uniqid());

        $stored = Donation::query()->whereKey($donation->id)->first();

        $this->assertSame('Secret Giver', $stored->guest_name);
        $this->assertSame('secret@example.com', $stored->guest_email);
        $this->assertSame('Secret Giver', $stored->donor_display_name);
    }
}
