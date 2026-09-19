<?php

namespace Tests\Feature\Donation;

use App\Models\Donation\Donation;
use App\Services\Donation\DonationService;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Donation\Exceptions\DonationValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-008 — Donation creation (docs/implementation/IMP-008-donation.md
 * AC-008-001..005/014/018/019, BR-1..BR-3/BR-12).
 */
class DonationServiceTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;
    use RefreshDatabase;

    public function test_guest_creates_a_pending_one_time_donation_with_unauthenticated_audit(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        $donation = app(DonationService::class)->create($campaign, [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'guest_name' => 'Guest Giver',
            'guest_email' => 'guest@example.com',
            'donor_display_name' => 'Guest Giver',
        ], null, 'guest-key-'.uniqid());

        $this->assertSame('PENDING', $donation->status);
        $this->assertNull($donation->donor_principal_id);
        $this->assertSame('Guest Giver', $donation->guest_name);
        $this->assertSame('guest@example.com', $donation->guest_email);
        $this->assertNotNull($donation->ulid);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'donation.created',
            'subject_id' => $donation->id,
            'actor_principal_kind' => 'unauthenticated',
            'actor_principal_id' => null,
            'execution_context' => 'http:donation:guest_created',
        ]);
    }

    public function test_authenticated_donor_creates_a_pending_donation_with_human_audit(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        $donation = app(DonationService::class)->create($campaign, [
            'amount_minor' => 2500,
            'currency' => 'USD',
            'donor_display_name' => 'Jane Donor',
        ], $actor, 'auth-key-'.uniqid());

        $this->assertSame('PENDING', $donation->status);
        $this->assertSame($actor->id, $donation->donor_principal_id);
        $this->assertNull($donation->guest_name);
        $this->assertNull($donation->guest_email);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'donation.created',
            'subject_id' => $donation->id,
            'actor_principal_kind' => 'human',
            'actor_principal_id' => $actor->id,
        ]);
    }

    public function test_creation_against_an_ineligible_campaign_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();

        foreach (['DRAFT', 'REVIEW', 'APPROVED', 'CLOSED'] as $status) {
            $campaign = $this->makeCampaignAtStatus($actor, $status);

            try {
                app(DonationService::class)->create($campaign, [
                    'amount_minor' => 1000,
                    'currency' => 'IDR',
                    'guest_name' => 'G',
                    'guest_email' => 'g@example.com',
                ], null, 'inelig-'.uniqid());

                $this->fail("Donation against {$status} campaign must be rejected.");
            } catch (DonationValidationException $e) {
                $this->assertSame('campaign_not_eligible', $e->reason);
            }
        }

        $this->assertSame(0, Donation::query()->count());
    }

    public function test_creation_against_a_published_but_out_of_window_campaign_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor, [
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(2)->toDateTimeString(),
        ]);

        $this->expectException(DonationValidationException::class);

        app(DonationService::class)->create($campaign, [
            'amount_minor' => 1000,
            'currency' => 'IDR',
            'guest_name' => 'G',
            'guest_email' => 'g@example.com',
        ], null, 'window-'.uniqid());
    }

    public function test_creation_with_an_unregistered_currency_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        $this->expectException(DonationValidationException::class);

        app(DonationService::class)->create($campaign, [
            'amount_minor' => 1000,
            'currency' => 'XXX',
            'guest_name' => 'G',
            'guest_email' => 'g@example.com',
        ], null, 'curr-'.uniqid());
    }

    public function test_creation_without_guest_identity_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        $this->expectException(DonationValidationException::class);

        app(DonationService::class)->create($campaign, [
            'amount_minor' => 1000,
            'currency' => 'IDR',
        ], null, 'noguest-'.uniqid());
    }

    public function test_authenticated_creation_with_guest_fields_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        $this->expectException(DonationValidationException::class);

        app(DonationService::class)->create($campaign, [
            'amount_minor' => 1000,
            'currency' => 'IDR',
            'guest_name' => 'G',
            'guest_email' => 'g@example.com',
        ], $actor, 'crosspath-'.uniqid());
    }

    public function test_creation_without_an_idempotency_key_is_rejected_first(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        $payload = [
            'amount_minor' => 1000,
            'currency' => 'IDR',
            'guest_name' => 'G',
            'guest_email' => 'g@example.com',
        ];

        foreach ([null, 12345] as $missingKey) {
            try {
                app(DonationService::class)->create($campaign, $payload, null, $missingKey);

                $this->fail('Missing idempotency key must be rejected.');
            } catch (DonationValidationException $e) {
                $this->assertSame('missing_idempotency_key', $e->reason);
            }
        }

        foreach (['', '   ', str_repeat('k', 129)] as $malformedKey) {
            try {
                app(DonationService::class)->create($campaign, $payload, null, $malformedKey);

                $this->fail('Malformed idempotency key must be rejected.');
            } catch (DonationValidationException $e) {
                $this->assertSame('invalid_idempotency_key', $e->reason);
            }
        }

        $this->assertSame(0, Donation::query()->count());
    }

    public function test_identical_key_with_matching_payload_returns_the_existing_donation(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);
        $key = 'replay-'.uniqid();

        $first = app(DonationService::class)->create($campaign, [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'guest_name' => 'Guest Giver',
            'guest_email' => 'guest@example.com',
        ], null, $key);

        $second = app(DonationService::class)->create($campaign, [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'guest_name' => 'Guest Giver',
            'guest_email' => 'guest@example.com',
        ], null, $key);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Donation::query()->count());
    }

    public function test_identical_key_with_differing_payload_is_rejected_with_conflict(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);
        $key = 'conflict-'.uniqid();

        app(DonationService::class)->create($campaign, [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'guest_name' => 'Guest Giver',
            'guest_email' => 'guest@example.com',
        ], null, $key);

        $this->expectException(DonationTransitionConflictException::class);

        app(DonationService::class)->create($campaign, [
            'amount_minor' => 75000,
            'currency' => 'IDR',
            'guest_name' => 'Guest Giver',
            'guest_email' => 'guest@example.com',
        ], null, $key);
    }

    public function test_guest_path_guard_rejects_neither_path_at_the_model_layer(): void
    {
        $this->expectException(\RuntimeException::class);

        $donation = new Donation;
        $donation->forceFill([
            'ulid' => (string) Str::ulid(),
            'campaign_id' => 1,
            'amount_minor' => 100,
            'currency' => 'IDR',
            'status' => 'PENDING',
            'idempotency_key' => 'guard-'.uniqid(),
        ]);
        $donation->save();
    }
}
