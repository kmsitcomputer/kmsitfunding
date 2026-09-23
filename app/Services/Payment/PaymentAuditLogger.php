<?php

namespace App\Services\Payment;

use App\Models\Rbac\Principal;
use App\Services\Audit\AuditEventInput;
use App\Services\Audit\AuditWriter;

/**
 * IMP-009 — thin sink wrapper over the canonical AuditWriter, mirroring
 * DonationAuditLogger exactly. One method per event registered in
 * PaymentAuditEventRegistrar. $actor is nullable ONLY for the two
 * ADR-003 guest cases (payment.attempt_created,
 * manual_transfer.evidence_submitted — pre-principal Unauthenticated
 * attribution with the registry-fixed execution context) — every other
 * event requires a resolved Principal.
 *
 * Metadata booleans are encoded as int 0/1: the registry's allow-list
 * type system supports only int|string|array (see
 * AuditWriter::assertValueShape) — widening it would modify the locked
 * IMP-004 contract, so the encoding stays here.
 */
class PaymentAuditLogger
{
    public function __construct(private readonly AuditWriter $writer) {}

    public function recordAttemptCreated(int $paymentId, array $metadata, ?Principal $actor): void
    {
        $this->emit('payment.attempt_created', 'payment', $paymentId, $metadata, $actor);
    }

    public function recordSucceeded(int $paymentId, array $metadata, Principal $actor): void
    {
        $this->emit('payment.succeeded', 'payment', $paymentId, $metadata, $actor);
    }

    public function recordFailed(int $paymentId, array $metadata, Principal $actor): void
    {
        $this->emit('payment.failed', 'payment', $paymentId, $metadata, $actor);
    }

    public function recordExpired(int $paymentId, array $metadata, Principal $actor): void
    {
        $this->emit('payment.expired', 'payment', $paymentId, $metadata, $actor);
    }

    public function recordCancelled(int $paymentId, array $metadata, Principal $actor): void
    {
        $this->emit('payment.cancelled', 'payment', $paymentId, $metadata, $actor);
    }

    public function recordDonationTransitionRejected(int $paymentId, array $metadata, Principal $actor): void
    {
        $this->emit('payment.donation_transition_rejected', 'payment', $paymentId, $metadata, $actor);
    }

    public function recordWebhookReceived(?int $eventId, array $metadata, Principal $actor): void
    {
        $this->emit('webhook.received', 'payment_provider_event', $eventId, $metadata, $actor);
    }

    public function recordWebhookVerificationFailed(array $metadata, Principal $actor): void
    {
        $this->emit('webhook.verification_failed', 'payment_provider_event', null, $metadata, $actor);
    }

    public function recordEvidenceSubmitted(int $evidenceId, array $metadata, ?Principal $actor): void
    {
        $this->emit('manual_transfer.evidence_submitted', 'manual_transfer_evidence', $evidenceId, $metadata, $actor);
    }

    public function recordApproved(int $paymentId, array $metadata, Principal $actor): void
    {
        $this->emit('manual_transfer.approved', 'payment', $paymentId, $metadata, $actor);
    }

    public function recordRejected(int $paymentId, array $metadata, Principal $actor): void
    {
        $this->emit('manual_transfer.rejected', 'payment', $paymentId, $metadata, $actor);
    }

    public function recordAmountMismatchHeld(int $paymentId, array $metadata, Principal $actor): void
    {
        $this->emit('manual_transfer.amount_mismatch_held', 'payment', $paymentId, $metadata, $actor);
    }

    public function recordCredentialChanged(int $credentialId, array $metadata, Principal $actor): void
    {
        $this->emit('provider_config.credential_changed', 'payment_provider_credential', $credentialId, $metadata, $actor);
    }

    private function emit(string $eventType, string $subjectType, ?int $subjectId, array $metadata, ?Principal $actor): void
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
