<?php

namespace Tests\Feature\Donation;

use App\Models\Donation\Donation;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Donation\DonationService;
use App\Services\Donation\DonationTransitionService;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Rbac\PrincipalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-008 — full Donation lifecycle transition matrix
 * (docs/implementation/IMP-008-donation.md "State / Lifecycle" / BR-7,
 * AC-008-006/007/008/010/017). System-consequence transitions are
 * exercised with a real System Principal, mirroring production
 * attribution; the sequential stale re-attempt proves the
 * status-predicate-under-lock guard (genuine multi-process locking is
 * DonationConcurrencyTest's MySQL concern).
 */
class DonationLifecycleTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;
    use RefreshDatabase;

    private function makePendingDonation(?Principal $donor = null): Donation
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        if ($donor !== null) {
            return app(DonationService::class)->create($campaign, [
                'amount_minor' => 10000,
                'currency' => 'IDR',
            ], $donor, 'lc-'.uniqid());
        }

        return app(DonationService::class)->create($campaign, [
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'guest_name' => 'G',
            'guest_email' => 'g@example.com',
        ], null, 'lc-'.uniqid());
    }

    private function makeSystemActor(): Principal
    {
        $catalog = SystemPrincipal::firstOrCreate(
            ['code' => 'test.donation.consequence'],
            ['description' => 'Test donation consequence identity.']
        );

        return app(PrincipalService::class)->forSystem($catalog);
    }

    public function test_pending_transitions_to_succeeded_with_audit(): void
    {
        $donation = $this->makePendingDonation();
        $system = $this->makeSystemActor();

        $succeeded = app(DonationTransitionService::class)->markSucceeded($donation, $system);

        $this->assertSame('SUCCEEDED', $succeeded->status);
        $this->assertNotNull($succeeded->succeeded_at);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'donation.succeeded',
            'subject_id' => $donation->id,
            'actor_principal_kind' => 'system',
            'actor_principal_id' => $system->id,
        ]);
    }

    public function test_pending_transitions_to_failed_with_audit(): void
    {
        $donation = $this->makePendingDonation();
        $system = $this->makeSystemActor();

        $failed = app(DonationTransitionService::class)->markFailed($donation, $system);

        $this->assertSame('FAILED', $failed->status);
        $this->assertNotNull($failed->failed_at);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'donation.failed',
            'subject_id' => $donation->id,
        ]);
    }

    public function test_pending_transitions_to_cancelled_with_actor_and_audit(): void
    {
        $donor = $this->makeUnauthorizedActor();
        $donation = $this->makePendingDonation($donor);

        $cancelled = app(DonationTransitionService::class)->cancel($donation, $donor);

        $this->assertSame('CANCELLED', $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame($donor->id, $cancelled->cancelled_by_principal_id);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'donation.cancelled',
            'subject_id' => $donation->id,
            'actor_principal_kind' => 'human',
            'actor_principal_id' => $donor->id,
        ]);
    }

    public function test_pending_transitions_to_expired_with_audit(): void
    {
        $donation = $this->makePendingDonation();
        $system = $this->makeSystemActor();

        $expired = app(DonationTransitionService::class)->markExpired($donation, $system);

        $this->assertSame('EXPIRED', $expired->status);
        $this->assertNotNull($expired->expired_at);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'donation.expired',
            'subject_id' => $donation->id,
        ]);
    }

    public function test_every_transition_from_a_terminal_state_is_rejected(): void
    {
        $system = $this->makeSystemActor();
        $service = app(DonationTransitionService::class);

        $succeeded = $service->markSucceeded($this->makePendingDonation(), $system)->fresh();
        $failed = $service->markFailed($this->makePendingDonation(), $system)->fresh();
        $expired = $service->markExpired($this->makePendingDonation(), $system)->fresh();

        $donor = $this->makeUnauthorizedActor();
        $cancelled = $service->cancel($this->makePendingDonation($donor), $donor)->fresh();

        foreach ([$succeeded, $failed, $expired, $cancelled] as $terminal) {
            foreach ([
                fn () => $service->markSucceeded($terminal, $system),
                fn () => $service->markFailed($terminal, $system),
                fn () => $service->markExpired($terminal, $system),
                fn () => $service->cancel($terminal, $donor),
            ] as $attempt) {
                try {
                    $attempt();
                    $this->fail("Transition from {$terminal->status} must be rejected.");
                } catch (DonationTransitionConflictException $e) {
                    $this->assertSame('invalid_transition', $e->reason);
                }
            }

            $this->assertSame($terminal->status, $terminal->fresh()->status);
        }
    }

    public function test_a_stale_second_transition_attempt_is_rejected(): void
    {
        $donation = $this->makePendingDonation();
        $system = $this->makeSystemActor();
        $service = app(DonationTransitionService::class);

        $service->markSucceeded($donation, $system);

        $this->expectException(DonationTransitionConflictException::class);

        $service->markFailed($donation, $system);
    }
}
