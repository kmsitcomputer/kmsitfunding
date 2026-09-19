<?php

namespace Tests\Feature\Donation;

use App\Models\Donation\Donation;
use App\Models\Donation\DonationRecurringOccurrence;
use App\Models\Donation\DonationRecurringPlan;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Donation\Exceptions\DonationValidationException;
use App\Services\Donation\RecurringPlanService;
use App\Support\Money\Exceptions\UnknownCurrencyException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-008 — Recurring Plan / Occurrence (docs/implementation/
 * IMP-008-donation.md AC-008-020..025, BR-6/BR-9/BR-10/BR-11,
 * HD-IMP008-01B/02/03).
 */
class RecurringPlanTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;
    use RefreshDatabase;

    private function makePlan(array $overrides = []): DonationRecurringPlan
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        return app(RecurringPlanService::class)->create($campaign, array_merge([
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'frequency' => 'MONTHLY',
        ], $overrides), $actor);
    }

    public function test_authenticated_donor_creates_a_monthly_plan_with_audit(): void
    {
        $plan = $this->makePlan();

        $this->assertSame('ACTIVE', $plan->status);
        $this->assertSame('MONTHLY', $plan->frequency);
        $this->assertNotNull($plan->ulid);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'donation.recurring_plan.created',
            'subject_id' => $plan->id,
            'actor_principal_kind' => 'human',
        ]);
    }

    public function test_non_monthly_frequency_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        foreach (['WEEKLY', 'YEARLY', 'CUSTOM', 'monthly', ''] as $frequency) {
            try {
                app(RecurringPlanService::class)->create($campaign, [
                    'amount_minor' => 50000,
                    'currency' => 'IDR',
                    'frequency' => $frequency,
                ], $actor);

                $this->fail("Frequency '{$frequency}' must be rejected.");
            } catch (DonationValidationException $e) {
                $this->assertSame('unsupported_frequency', $e->reason);
            }
        }

        $this->assertSame(0, DonationRecurringPlan::query()->count());
    }

    public function test_owning_donor_may_pause_resume_and_cancel(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);
        $service = app(RecurringPlanService::class);
        $plan = $service->create($campaign, [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'frequency' => 'MONTHLY',
        ], $actor);

        $paused = $service->pause($plan, $actor);

        $this->assertSame('PAUSED', $paused->status);
        $this->assertNotNull($paused->paused_at);
        $this->assertSame($actor->id, $paused->paused_by_principal_id);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'donation.recurring_plan.paused',
            'subject_id' => $plan->id,
        ]);

        $resumed = $service->resume($paused->fresh(), $actor);

        $this->assertSame('ACTIVE', $resumed->status);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'donation.recurring_plan.resumed',
            'subject_id' => $plan->id,
        ]);

        $cancelled = $service->cancel($resumed->fresh(), $actor);

        $this->assertSame('CANCELLED', $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'donation.recurring_plan.cancelled',
            'subject_id' => $plan->id,
        ]);
    }

    public function test_pause_rejects_a_non_active_plan_and_resume_rejects_a_non_paused_plan(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(RecurringPlanService::class);
        $plan = $this->makePlan();

        $this->expectException(DonationTransitionConflictException::class);
        $service->resume($plan, $actor);
    }

    public function test_occurrence_generation_creates_one_donation_and_advances_the_schedule(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);
        $service = app(RecurringPlanService::class);
        $plan = $service->create($campaign, [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'frequency' => 'MONTHLY',
        ], $actor);
        $previousNext = $plan->next_occurrence_at;

        $occurrence = $service->generateOccurrence($plan, [], $actor);

        $this->assertSame('GENERATED', $occurrence->status);
        $this->assertNotNull($occurrence->donation_id);
        $this->assertNotNull($occurrence->generated_at);

        $donation = $occurrence->fresh()->donation;

        $this->assertSame('PENDING', $donation->status);
        $this->assertSame($actor->id, $donation->donor_principal_id);
        $this->assertSame($occurrence->id, $donation->recurring_occurrence_id);
        $this->assertSame($plan->amount_minor, $donation->amount_minor);

        $this->assertTrue($plan->fresh()->next_occurrence_at->gt($previousNext));
    }

    public function test_generation_against_an_inactive_plan_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(RecurringPlanService::class);
        $plan = $this->makePlan();
        $service->pause($plan, $actor);

        $this->expectException(DonationTransitionConflictException::class);
        $service->generateOccurrence($plan->fresh(), [], $actor);
    }

    public function test_failed_occurrence_is_never_retried_and_next_scheduled_proceeds(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(RecurringPlanService::class);
        $plan = $this->makePlan();

        $scheduled = new DonationRecurringOccurrence;
        $scheduled->forceFill([
            'recurring_plan_id' => $plan->id,
            'scheduled_at' => now(),
            'status' => 'SCHEDULED',
        ]);
        $scheduled->save();

        $failed = $service->markOccurrenceFailed($scheduled, 'child donation could not complete', $actor);

        $this->assertSame('FAILED', $failed->status);
        $this->assertNotNull($failed->failure_reason);

        $next = $service->generateOccurrence($plan->fresh(), [], $actor);

        $this->assertSame('GENERATED', $next->status);
        $this->assertNotSame($failed->id, $next->id);
        $this->assertSame('FAILED', $failed->fresh()->status);
    }

    public function test_marking_a_non_scheduled_occurrence_failed_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(RecurringPlanService::class);
        $plan = $this->makePlan();
        $occurrence = $service->generateOccurrence($plan, [], $actor);

        $this->expectException(DonationTransitionConflictException::class);
        $service->markOccurrenceFailed($occurrence->fresh(), 'too late', $actor);
    }

    public function test_plan_creation_with_an_unregistered_currency_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);

        $this->expectException(UnknownCurrencyException::class);

        app(RecurringPlanService::class)->create($campaign, [
            'amount_minor' => 50000,
            'currency' => 'XXX',
            'frequency' => 'MONTHLY',
        ], $actor);
    }

    public function test_occurrence_donation_id_uniqueness_is_enforced_by_the_database(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(RecurringPlanService::class);
        $plan = $this->makePlan();
        $occurrence = $service->generateOccurrence($plan, [], $actor);

        $second = new DonationRecurringOccurrence;
        $second->forceFill([
            'recurring_plan_id' => $plan->id,
            'scheduled_at' => now(),
            'donation_id' => $occurrence->donation_id,
            'status' => 'GENERATED',
        ]);

        $this->expectException(QueryException::class);
        $second->save();
    }

    public function test_generated_donation_carries_plan_money_and_anonymity(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);
        $plan = app(RecurringPlanService::class)->create($campaign, [
            'amount_minor' => 1500,
            'currency' => 'JPY',
            'frequency' => 'MONTHLY',
            'is_anonymous' => true,
        ], $actor);

        $occurrence = app(RecurringPlanService::class)->generateOccurrence($plan, [], $actor);
        $donation = $occurrence->fresh()->donation;

        $this->assertSame(1500, $donation->amount_minor);
        $this->assertSame('JPY', $donation->currency);
        $this->assertTrue($donation->is_anonymous);
    }

    public function test_repeated_sweep_against_the_same_due_plan_does_not_duplicate(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(RecurringPlanService::class);
        $plan = $this->makePlan();

        $first = $service->generateOccurrence($plan, [], $actor);

        $this->assertSame('GENERATED', $first->status);

        // The plan's schedule has advanced past the first execution time,
        // so a repeated sweep with the SAME execution time is a
        // deterministic already-processed no-op — never a second
        // Occurrence + Donation for the same logical due occurrence.
        try {
            $service->generateOccurrence($plan->fresh(), ['as_of' => $plan->next_occurrence_at], $actor);

            $this->fail('A repeated sweep of an already-generated due point must be rejected.');
        } catch (DonationTransitionConflictException $e) {
            $this->assertSame('occurrence_already_processed', $e->reason);
        }

        $this->assertSame(1, DonationRecurringOccurrence::query()->where('recurring_plan_id', $plan->id)->count());
        $this->assertSame(1, Donation::query()->where('campaign_id', $plan->campaign_id)->count());
    }

    public function test_generation_against_a_cancelled_plan_generates_nothing(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(RecurringPlanService::class);
        $plan = $this->makePlan();
        $service->cancel($plan, $actor);

        $this->expectException(DonationTransitionConflictException::class);
        $service->generateOccurrence($plan->fresh(), [], $actor);
    }

    public function test_plan_creation_alone_creates_no_donation_row(): void
    {
        $plan = $this->makePlan();

        $this->assertSame(
            0,
            Donation::query()->where('campaign_id', $plan->campaign_id)->count(),
            'Creating a plan must not create any Donation row — only occurrence generation does.'
        );
    }
}
