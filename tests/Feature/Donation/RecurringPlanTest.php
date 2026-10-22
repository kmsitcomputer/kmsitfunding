<?php

namespace Tests\Feature\Donation;

use App\Models\Donation\Donation;
use App\Models\Donation\DonationRecurringOccurrence;
use App\Models\Donation\DonationRecurringPlan;
use App\Services\Donation\DonationService;
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
 *
 * The concrete occurrence SCHEDULING/EXECUTION engine is explicitly
 * deferred (spec "Out of Scope"): no behavioral generation-engine tests
 * exist here — only the approved domain model / lifecycle / persistence /
 * authorization contracts (create, pause/resume/cancel, BR-9 frequency
 * allow-list, BR-11 FAILED-terminal state rule, donation_id uniqueness).
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

    public function test_a_failed_occurrence_is_terminal_state_only(): void
    {
        $plan = $this->makePlan();

        $scheduled = new DonationRecurringOccurrence;
        $scheduled->forceFill([
            'recurring_plan_id' => $plan->id,
            'scheduled_at' => now(),
            'status' => 'SCHEDULED',
        ]);
        $scheduled->save();

        $scheduled->forceFill([
            'status' => 'FAILED',
            'failure_reason' => 'child donation could not complete',
        ])->save();

        $failed = $scheduled->fresh();

        $this->assertSame('FAILED', $failed->status);
        $this->assertNotNull($failed->failure_reason);

        // BR-11/HD-IMP008-03 state rule: FAILED is terminal — no IMP-008
        // lifecycle path revives it, and the plan itself remains governed
        // only by pause/resume/cancel.
        $this->assertSame('ACTIVE', $plan->fresh()->status);
        $this->assertSame('FAILED', $failed->fresh()->status);
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
        $plan = $this->makePlan();

        $donation = app(DonationService::class)->create($plan->campaign, [
            'amount_minor' => 50000,
            'currency' => 'IDR',
        ], $actor, 'occuniq-'.uniqid());

        $occurrence = new DonationRecurringOccurrence;
        $occurrence->forceFill([
            'recurring_plan_id' => $plan->id,
            'scheduled_at' => now(),
            'donation_id' => $donation->id,
            'status' => 'GENERATED',
        ]);
        $occurrence->save();

        $second = new DonationRecurringOccurrence;
        $second->forceFill([
            'recurring_plan_id' => $plan->id,
            'scheduled_at' => now(),
            'donation_id' => $donation->id,
            'status' => 'GENERATED',
        ]);

        $this->expectException(QueryException::class);
        $second->save();
    }

    public function test_plan_creation_alone_creates_no_donation_row(): void
    {
        $plan = $this->makePlan();

        $this->assertSame(
            0,
            Donation::query()->where('campaign_id', $plan->campaign_id)->count(),
            'Creating a plan must not create any Donation row — the occurrence generation engine is deferred.'
        );
    }
}
