<?php

namespace App\Services\Donation;

use App\Models\Campaign\Campaign;
use App\Models\Donation\DonationRecurringPlan;
use App\Models\Rbac\Principal;
use App\Services\Campaign\CampaignEligibilityResolver;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Donation\Exceptions\DonationValidationException;
use App\Support\Money\CurrencyMinorUnits;
use App\Support\Money\Exceptions\UnknownCurrencyException;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * IMP-008 — Recurring Plan lifecycle (docs/implementation/
 * IMP-008-donation.md "Domain Model", BR-6/BR-9/BR-10/BR-11,
 * HD-IMP008-01B/HD-IMP008-02/HD-IMP008-03).
 *
 * Authenticated-donor-only (a NULL actor is rejected — guest recurring
 * is NOT supported, BR-6). Frequency accepts exactly the configured
 * allow-list values (v1: MONTHLY only) via a validated-list check
 * structured for future extension without redesign (BR-9). Pause/resume/
 * cancel lock the Plan row first and re-verify status under that lock.
 *
 * The concrete recurring occurrence SCHEDULING/EXECUTION engine (what
 * moves a SCHEDULED Occurrence to GENERATED — the actual cron/job that
 * creates the child Donation on schedule) is explicitly deferred to a
 * later IMP (spec "Out of Scope"): this service owns create/pause/resume/
 * cancel only and performs no automatic occurrence generation. The
 * locked operational rules the engine will one day consume — MONTHLY-only
 * frequency, pause/resume/cancel authority, a FAILED Occurrence is never
 * auto-retried (BR-11, HD-IMP008-03) — are defined by the specification,
 * and the occurrence tables exist now as a deliberate minimal foundation.
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
}
