<?php

namespace App\Services\Donation;

use App\Models\Campaign\Campaign;
use App\Models\Donation\Donation;
use App\Models\Donation\DonationRecurringOccurrence;
use App\Models\Donation\DonationRecurringPlan;
use App\Models\Rbac\Principal;
use App\Services\Campaign\CampaignEligibilityResolver;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Donation\Exceptions\DonationValidationException;
use App\Support\Money\CurrencyMinorUnits;
use App\Support\Money\Exceptions\UnknownCurrencyException;
use App\Support\Money\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * IMP-008 — Recurring Plan lifecycle + Occurrence generation
 * (docs/implementation/IMP-008-donation.md "Domain Model", BR-6/BR-9/
 * BR-10/BR-11, HD-IMP008-01B/HD-IMP008-02/HD-IMP008-03).
 *
 * Authenticated-donor-only (a NULL actor is rejected — guest recurring
 * is NOT supported, BR-6). Frequency accepts exactly the configured
 * allow-list values (v1: MONTHLY only) via a validated-list check
 * structured for future extension without redesign (BR-9). Pause/resume/
 * cancel lock the Plan row first and re-verify status under that lock.
 * A FAILED Occurrence is never auto-retried (BR-11); generation of one
 * Occurrence is guarded by the donation_id unique constraint so two
 * concurrent generations cannot both succeed.
 *
 * WHO may call what is a Policy-layer concern (RecurringPlanPolicy):
 * the owning donor (OWN scope) and the ORGANIZATION-scoped admin
 * override share these same methods.
 */
class RecurringPlanService
{
    public function __construct(
        private readonly CampaignEligibilityResolver $eligibility,
        private readonly DonationAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{amount_minor:int,currency:string,frequency:string,is_anonymous?:bool,starts_at?:mixed,ends_at?:mixed}  $payload
     */
    public function create(Campaign $campaign, array $payload, Principal $actor): DonationRecurringPlan
    {
        $this->assertValidFrequency($payload['frequency'] ?? null);
        $this->assertValidMoney($payload['amount_minor'] ?? null, $payload['currency'] ?? null);

        if (! is_int($payload['amount_minor'] ?? null) || ($payload['amount_minor'] ?? 0) <= 0) {
            throw new DonationValidationException(
                'invalid_amount',
                'amount_minor must be a positive integer.'
            );
        }

        $campaign = Campaign::query()->whereKey($campaign->id)->firstOrFail();

        if (! $this->eligibility->isDonationEligible($campaign)) {
            throw new DonationValidationException(
                'campaign_not_eligible',
                "Campaign {$campaign->id} is not currently eligible to receive donations."
            );
        }

        return DB::transaction(function () use ($campaign, $payload, $actor) {
            $plan = new DonationRecurringPlan;
            $plan->forceFill([
                'donor_principal_id' => $actor->id,
                'campaign_id' => $campaign->id,
                'amount_minor' => $payload['amount_minor'],
                'currency' => $payload['currency'],
                'frequency' => $payload['frequency'],
                'status' => 'ACTIVE',
                'is_anonymous' => (bool) ($payload['is_anonymous'] ?? false),
                'starts_at' => $payload['starts_at'] ?? now(),
                'ends_at' => $payload['ends_at'] ?? null,
                'next_occurrence_at' => $payload['starts_at'] ?? now(),
            ]);
            $plan->save();

            $this->auditLogger->recordRecurringPlanCreated($plan->id, [
                'campaign_id' => $campaign->id,
                'amount_minor' => $plan->amount_minor,
                'currency' => $plan->currency,
                'frequency' => $plan->frequency,
                'is_anonymous' => $plan->is_anonymous ? 1 : 0,
            ], $actor);

            return $plan->fresh();
        });
    }

    public function pause(DonationRecurringPlan $plan, Principal $actor): DonationRecurringPlan
    {
        return DB::transaction(function () use ($plan, $actor) {
            $locked = $this->lockedIn($plan, ['ACTIVE'], 'pause');

            $locked->forceFill([
                'status' => 'PAUSED',
                'paused_at' => now(),
                'paused_by_principal_id' => $actor->id,
            ])->save();

            $this->auditLogger->recordRecurringPlanPaused($locked->id, [
                'donation_recurring_plan_ulid' => $locked->ulid,
                'paused_by_principal_id' => $actor->id,
            ], $actor);

            return $locked;
        });
    }

    public function resume(DonationRecurringPlan $plan, Principal $actor): DonationRecurringPlan
    {
        return DB::transaction(function () use ($plan, $actor) {
            $locked = $this->lockedIn($plan, ['PAUSED'], 'resume');

            $locked->forceFill([
                'status' => 'ACTIVE',
                'paused_at' => null,
                'paused_by_principal_id' => null,
            ])->save();

            $this->auditLogger->recordRecurringPlanResumed($locked->id, [
                'donation_recurring_plan_ulid' => $locked->ulid,
            ], $actor);

            return $locked;
        });
    }

    public function cancel(DonationRecurringPlan $plan, Principal $actor): DonationRecurringPlan
    {
        return DB::transaction(function () use ($plan, $actor) {
            $locked = $this->lockedIn($plan, ['ACTIVE', 'PAUSED'], 'cancel');

            $locked->forceFill([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
                'cancelled_by_principal_id' => $actor->id,
            ])->save();

            $this->auditLogger->recordRecurringPlanCancelled($locked->id, [
                'donation_recurring_plan_ulid' => $locked->ulid,
                'cancelled_by_principal_id' => $actor->id,
            ], $actor);

            return $locked;
        });
    }

    /**
     * Generates the child Donation for the plan's next due occurrence:
     * persists one SCHEDULED Occurrence, creates its Donation through
     * DonationService (campaign eligibility re-checked at creation time),
     * marks the Occurrence GENERATED, and advances next_occurrence_at by
     * one month — all atomically. A child-creation failure rolls the whole
     * attempt back (no orphan SCHEDULED row, no partial Donation); the
     * plan's schedule is simply re-attempted by the next sweep — mirroring
     * the CMS "transient failures leave due work pending" precedent. To
     * record a PERSISTENT failure, generate the SCHEDULED row first (via a
     * sweep that persists it outside this transaction) and call
     * markOccurrenceFailed() — IMP-008 never retries a FAILED occurrence
     * (BR-11, HD-IMP008-03).
     *
     * Concurrency (IMP008-REVIEW-02): due plans are selected OUTSIDE any
     * lock, so two workers may both observe the same due plan. The lock
     * serializes them; the loser MUST NOT generate again. After
     * lockForUpdate the canonical plan is re-read and BOTH conditions are
     * re-checked: ACTIVE status AND due-ness against the authoritative
     * execution time ($payload['as_of'], default now()). A worker that
     * already advanced next_occurrence_at past that time wins; the loser
     * observes it and throws the typed already-processed conflict —
     * a deterministic no-op, never a second Occurrence + Donation.
     * next_occurrence_at therefore advances exactly once per logical due
     * occurrence. Scheduler withoutOverlapping() is a courtesy only, never
     * the correctness mechanism.
     *
     * Database backstop: the child Donation's idempotency key is derived
     * from the STABLE consumed due point (the locked plan's own
     * next_occurrence_at), not the fresh occurrence ULID — so the
     * spec-mandated donations.idempotency_key UNIQUE constraint covers
     * one logical scheduled occurrence even if two writers ever pass the
     * due check with the same stored due point. No new business
     * recurrence model is introduced: frequency remains MONTHLY, and no
     * new schema constraint is invented on caller-controlled timestamps.
     *
     * @param  array{scheduled_at?:mixed,as_of?:mixed}  $payload
     */
    public function generateOccurrence(DonationRecurringPlan $plan, array $payload, Principal $actor): DonationRecurringOccurrence
    {
        return DB::transaction(function () use ($plan, $payload) {
            $lockedPlan = DonationRecurringPlan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail();

            if ($lockedPlan->status !== 'ACTIVE') {
                throw new DonationTransitionConflictException(
                    'plan_not_active',
                    "Recurring Plan {$lockedPlan->id} is status={$lockedPlan->status}; only ACTIVE plans generate occurrences."
                );
            }

            $asOf = isset($payload['as_of']) ? Carbon::parse($payload['as_of']) : now();
            $duePoint = $lockedPlan->next_occurrence_at !== null
                ? Carbon::parse($lockedPlan->next_occurrence_at)
                : null;

            if ($duePoint !== null && $duePoint->gt($asOf)) {
                throw new DonationTransitionConflictException(
                    'occurrence_already_processed',
                    "Recurring Plan {$lockedPlan->id} already generated its due occurrence (next_occurrence_at is past the execution time)."
                );
            }

            $scheduledAt = $payload['scheduled_at'] ?? $asOf;

            $donor = $this->resolvePlanDonor($lockedPlan);

            $occurrence = new DonationRecurringOccurrence;
            $occurrence->forceFill([
                'recurring_plan_id' => $lockedPlan->id,
                'scheduled_at' => $scheduledAt,
                'status' => 'SCHEDULED',
            ]);
            $occurrence->save();

            try {
                $dueKey = ($duePoint ?? $asOf)->format('Y-m-d H:i:s');
                $idempotencyKey = 'recurring-'.$lockedPlan->ulid.'-'.$dueKey;

                $donation = app(DonationService::class)->create(
                    Campaign::query()->whereKey($lockedPlan->campaign_id)->firstOrFail(),
                    [
                        'amount_minor' => $lockedPlan->amount_minor,
                        'currency' => $lockedPlan->currency,
                        'is_anonymous' => $lockedPlan->is_anonymous,
                        'recurring_occurrence_id' => $occurrence->id,
                    ],
                    $donor,
                    $idempotencyKey,
                );
            } catch (QueryException $e) {
                $driverCode = $e->errorInfo[1] ?? null;

                if (in_array($driverCode, [1062, 19, 2067], true)) {
                    throw new DonationTransitionConflictException(
                        'occurrence_already_generated',
                        "Occurrence {$occurrence->id} already generated its donation."
                    );
                }

                throw $e;
            }

            try {
                $occurrence->forceFill([
                    'donation_id' => $donation->id,
                    'status' => 'GENERATED',
                    'generated_at' => now(),
                ])->save();
            } catch (QueryException $e) {
                $driverCode = $e->errorInfo[1] ?? null;

                if (in_array($driverCode, [1062, 19, 2067], true)) {
                    throw new DonationTransitionConflictException(
                        'occurrence_already_generated',
                        "Occurrence {$occurrence->id} already generated its donation."
                    );
                }

                throw $e;
            }

            $lockedPlan->forceFill([
                'next_occurrence_at' => $this->nextMonthlyOccurrence($lockedPlan),
            ])->save();

            return $occurrence->fresh();
        });
    }

    /**
     * Records a FAILED outcome against one SCHEDULED Occurrence (e.g. the
     * child Donation creation could not complete). Terminal: IMP-008
     * never retries it; the plan's next SCHEDULED occurrence proceeds
     * independently (BR-11, HD-IMP008-03).
     */
    public function markOccurrenceFailed(DonationRecurringOccurrence $occurrence, string $reason, Principal $actor): DonationRecurringOccurrence
    {
        return DB::transaction(function () use ($occurrence, $reason) {
            $locked = DonationRecurringOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'SCHEDULED') {
                throw new DonationTransitionConflictException(
                    'invalid_transition',
                    "Occurrence {$locked->id} is status={$locked->status}; only SCHEDULED may be marked FAILED."
                );
            }

            $locked->forceFill([
                'status' => 'FAILED',
                'failure_reason' => substr($reason, 0, 1000),
            ])->save();

            return $locked;
        });
    }

    private function lockedIn(DonationRecurringPlan $plan, array $allowedStatuses, string $action): DonationRecurringPlan
    {
        $locked = DonationRecurringPlan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail();

        if (! in_array($locked->status, $allowedStatuses, true)) {
            throw new DonationTransitionConflictException(
                'invalid_transition',
                "Recurring Plan {$locked->id} is status={$locked->status}; cannot {$action} from this status."
            );
        }

        return $locked;
    }

    private function assertValidFrequency(mixed $frequency): void
    {
        $allowed = config('donation.recurring_frequencies', ['MONTHLY']);

        if (! is_string($frequency) || ! in_array($frequency, $allowed, true)) {
            throw new DonationValidationException(
                'unsupported_frequency',
                'A recurring plan frequency must be one of: '.implode(', ', (array) $allowed).'.'
            );
        }
    }

    private function assertValidMoney(mixed $amount, mixed $currency): void
    {
        if (! is_string($currency) || ! CurrencyMinorUnits::isRegistered($currency)) {
            throw new UnknownCurrencyException(is_string($currency) ? $currency : '');
        }

        Money::ofMinorUnits(0, $currency);
    }

    private function resolvePlanDonor(DonationRecurringPlan $plan): Principal
    {
        return Principal::query()->whereKey($plan->donor_principal_id)->firstOrFail();
    }

    private function nextMonthlyOccurrence(DonationRecurringPlan $plan): mixed
    {
        $base = $plan->next_occurrence_at ?? now();

        return Carbon::parse($base)->addMonthNoOverflow();
    }
}
