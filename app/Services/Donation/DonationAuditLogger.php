<?php

namespace App\Services\Donation;

use App\Models\Rbac\Principal;
use App\Services\Audit\AuditEventInput;
use App\Services\Audit\AuditWriter;

/**
 * IMP-008 — thin sink wrapper over the canonical AuditWriter, mirroring
 * CampaignAuditLogger exactly. One method per event registered in
 * DonationAuditEventRegistrar. $actor is nullable ONLY for the guest
 * donation.created case (ADR-002: pre-principal Unauthenticated
 * attribution with the registry-fixed execution context) — every other
 * event requires a resolved Principal.
 */
class DonationAuditLogger
{
    public function __construct(private readonly AuditWriter $writer) {}

    public function recordDonationCreated(int $donationId, array $metadata, ?Principal $actor): void
    {
        $this->emit('donation.created', 'donation', $donationId, $metadata, $actor);
    }

    public function recordDonationSucceeded(int $donationId, array $metadata, Principal $actor): void
    {
        $this->emit('donation.succeeded', 'donation', $donationId, $metadata, $actor);
    }

    public function recordDonationFailed(int $donationId, array $metadata, Principal $actor): void
    {
        $this->emit('donation.failed', 'donation', $donationId, $metadata, $actor);
    }

    public function recordDonationExpired(int $donationId, array $metadata, Principal $actor): void
    {
        $this->emit('donation.expired', 'donation', $donationId, $metadata, $actor);
    }

    public function recordDonationCancelled(int $donationId, array $metadata, Principal $actor): void
    {
        $this->emit('donation.cancelled', 'donation', $donationId, $metadata, $actor);
    }

    public function recordRecurringPlanCreated(int $planId, array $metadata, Principal $actor): void
    {
        $this->emit('donation.recurring_plan.created', 'donation_recurring_plan', $planId, $metadata, $actor);
    }

    public function recordRecurringPlanPaused(int $planId, array $metadata, Principal $actor): void
    {
        $this->emit('donation.recurring_plan.paused', 'donation_recurring_plan', $planId, $metadata, $actor);
    }

    public function recordRecurringPlanResumed(int $planId, array $metadata, Principal $actor): void
    {
        $this->emit('donation.recurring_plan.resumed', 'donation_recurring_plan', $planId, $metadata, $actor);
    }

    public function recordRecurringPlanCancelled(int $planId, array $metadata, Principal $actor): void
    {
        $this->emit('donation.recurring_plan.cancelled', 'donation_recurring_plan', $planId, $metadata, $actor);
    }

    private function emit(string $eventType, string $subjectType, int $subjectId, array $metadata, ?Principal $actor): void
    {
        $this->writer->record(new AuditEventInput(
            eventType: $eventType,
            actor: $actor,
            subjectType: $subjectType,
            subjectId: $subjectId,
            metadata: $metadata,
        ));
    }
}
