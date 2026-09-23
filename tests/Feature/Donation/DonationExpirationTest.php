<?php

namespace Tests\Feature\Donation;

use App\Models\Donation\Donation;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Donation\DonationService;
use App\Services\Donation\DonationTransitionService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-008 — expiration mechanism (docs/implementation/IMP-008-donation.md
 * "State / Lifecycle", HD-IMP008-04): config-gated sweep; disabled while
 * the duration is unset (no invented default); enabled expiry transitions
 * PENDING -> EXPIRED with audit; non-pending rows untouched.
 */
class DonationExpirationTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;
    use RefreshDatabase;

    private function seedSchedulerPrincipal(): Principal
    {
        $catalog = SystemPrincipal::firstOrCreate(
            ['code' => 'donation.scheduler'],
            ['description' => 'Test donation scheduler identity.']
        );

        return app(PrincipalService::class)->forSystem($catalog);
    }

    private function makePendingDonation(?\DateTimeInterface $createdAt = null): Donation
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        $donation = app(DonationService::class)->create($campaign, [
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'guest_name' => 'G',
            'guest_email' => 'g@example.com',
        ], null, 'exp-'.uniqid());

        if ($createdAt !== null) {
            $donation->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $donation->fresh();
    }

    public function test_sweep_does_nothing_while_no_duration_is_configured(): void
    {
        config()->set('donation.pending_expiry_minutes', null);
        $this->seedSchedulerPrincipal();

        $donation = $this->makePendingDonation(now()->subDays(30));

        $exit = Artisan::call('donation:expire-pending');

        $this->assertSame(0, $exit);
        $this->assertSame('PENDING', $donation->fresh()->status);
        $this->assertDatabaseMissing('audit_records', ['event_type' => 'donation.expired']);
    }

    public function test_sweep_expires_only_donations_older_than_the_configured_window(): void
    {
        config()->set('donation.pending_expiry_minutes', 60);
        $this->seedSchedulerPrincipal();

        $stale = $this->makePendingDonation(now()->subHours(2));
        $fresh = $this->makePendingDonation(now()->subMinutes(10));

        $exit = Artisan::call('donation:expire-pending');

        $this->assertSame(0, $exit);
        $this->assertSame('EXPIRED', $stale->fresh()->status);
        $this->assertNotNull($stale->fresh()->expired_at);
        $this->assertSame('PENDING', $fresh->fresh()->status);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'donation.expired',
            'subject_id' => $stale->id,
            'actor_principal_kind' => 'system',
        ]);
    }

    public function test_sweep_leaves_non_pending_donations_untouched(): void
    {
        config()->set('donation.pending_expiry_minutes', 60);
        $system = $this->seedSchedulerPrincipal();

        $donation = $this->makePendingDonation(now()->subHours(2));
        app(DonationTransitionService::class)->markSucceeded($donation, $system);

        Artisan::call('donation:expire-pending');

        $this->assertSame('SUCCEEDED', $donation->fresh()->status);
    }

    public function test_sweep_fails_closed_without_a_seeded_scheduler_principal(): void
    {
        config()->set('donation.pending_expiry_minutes', 60);

        $exit = Artisan::call('donation:expire-pending');

        $this->assertSame(1, $exit);
    }
}
