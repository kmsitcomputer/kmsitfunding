<?php

namespace App\Services\Payment;

use App\Models\Payment\ManualTransferEvidence;
use App\Models\Payment\Payment;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Donation\DonationTransitionService;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Payment\Exceptions\PaymentTransitionConflictException;
use App\Services\Payment\Exceptions\PaymentValidationException;
use App\Services\Rbac\PrincipalService;
use Illuminate\Support\Facades\DB;

/**
 * IMP-009 — Manual Transfer admin verification
 * (docs/implementation/IMP-009-payment-hub.md "Manual Transfer" /
 * HD-IMP009-06/07, "Donation Integration", BR-12/BR-21/BR-22).
 * Authorization (payment.manual_transfer.verify + ORGANIZATION +
 * financial_approver) is a Policy-layer concern — this service
 * enforces the domain contract:
 *
 * APPROVED requires at least one evidence row (HD-IMP009-06); sets
 * verified_by_principal_id, moves Payment -> SUCCEEDED, and REQUESTS
 * the Donation transition through the existing System-Principal-gated
 * surface (system.payment-outcome-consequence, HD-IMP009-11) in the
 * SAME transaction — never a direct donations mutation. REJECTED
 * (allowed with zero evidence) moves Payment -> FAILED with
 * MANUAL_REJECTED and requests the Donation failure surface
 * symmetrically. AMOUNT_MISMATCH_HOLD (declared/observed !=
 * payments.amount_minor, HD-IMP009-07) records the held outcome at the
 * evidence layer only — Payment stays PENDING, Donation untouched,
 * both amount columns immutable (BR-12), no refund/credit/balance/
 * allocation invented.
 *
 * A rejected Donation transition (already-terminal Donation) is
 * caught and recorded via payment.donation_transition_rejected
 * (CRITICAL) for mandatory review — never forced (HD-IMP009-02).
 */
class ManualTransferVerificationService
{
    public function __construct(
        private readonly PaymentTransitionService $transitions,
        private readonly DonationTransitionService $donationTransitions,
        private readonly PaymentAuditLogger $auditLogger,
        private readonly PrincipalService $principals,
    ) {}

    public function approve(Payment $payment, Principal $verifier, int $evidenceId, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($payment, $verifier, $evidenceId, $notes) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedDonation = $lockedPayment->donation()->lockForUpdate()->firstOrFail();

            $this->assertPendingManual($lockedPayment);
            $evidence = ManualTransferEvidence::query()
                ->whereKey($evidenceId)
                ->where('payment_id', $lockedPayment->id)
                ->lockForUpdate()
                ->first();

            if ($evidence === null) {
                throw new PaymentValidationException('evidence_not_found', 'The referenced evidence does not belong to this payment.');
            }

            if ($evidence->isReviewed()) {
                throw new PaymentTransitionConflictException('evidence_already_reviewed', 'This evidence has already been reviewed.');
            }

            $evidence->forceFill([
                'reviewed_by_principal_id' => $verifier->id,
                'reviewed_at' => now(),
                'review_outcome' => 'APPROVED',
                'review_notes' => $notes,
            ])->save();

            $lockedPayment->forceFill(['verified_by_principal_id' => $verifier->id])->save();

            // The Payment-outcome event (payment.succeeded) admits
            // Integration/System actors only — the human decision is
            // captured by manual_transfer.approved below; the outcome
            // itself resolves under the consequence System Principal.
            $consequenceActor = $this->consequencePrincipal();

            $this->transitions->markSucceeded($lockedPayment, $consequenceActor);

            $this->auditLogger->recordApproved($lockedPayment->id, [
                'payment_ulid' => $lockedPayment->ulid,
                'verified_by_principal_id' => $verifier->id,
            ], $verifier);

            $this->invokeDonationConsequence($lockedPayment->fresh(), $lockedDonation, true, $consequenceActor);

            return $lockedPayment->fresh();
        });
    }

    public function reject(Payment $payment, Principal $verifier, ?int $evidenceId = null, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($payment, $verifier, $evidenceId, $notes) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedDonation = $lockedPayment->donation()->lockForUpdate()->firstOrFail();

            $this->assertPendingManual($lockedPayment);

            if ($evidenceId !== null) {
                $evidence = ManualTransferEvidence::query()
                    ->whereKey($evidenceId)
                    ->where('payment_id', $lockedPayment->id)
                    ->lockForUpdate()
                    ->first();

                if ($evidence === null) {
                    throw new PaymentValidationException('evidence_not_found', 'The referenced evidence does not belong to this payment.');
                }

                if ($evidence->isReviewed()) {
                    throw new PaymentTransitionConflictException('evidence_already_reviewed', 'This evidence has already been reviewed.');
                }

                $evidence->forceFill([
                    'reviewed_by_principal_id' => $verifier->id,
                    'reviewed_at' => now(),
                    'review_outcome' => 'REJECTED',
                    'review_notes' => $notes,
                ])->save();
            }

            $consequenceActor = $this->consequencePrincipal();

            $this->transitions->markFailed($lockedPayment, $consequenceActor, 'MANUAL_REJECTED');

            $this->auditLogger->recordRejected($lockedPayment->id, [
                'payment_ulid' => $lockedPayment->ulid,
                'verified_by_principal_id' => $verifier->id,
                'review_notes' => (string) $notes,
            ], $verifier);

            $this->invokeDonationConsequence($lockedPayment->fresh(), $lockedDonation, false, $consequenceActor);

            return $lockedPayment->fresh();
        });
    }

    public function holdForAmountMismatch(Payment $payment, Principal $verifier, int $evidenceId, ?string $notes = null): ManualTransferEvidence
    {
        return DB::transaction(function () use ($payment, $verifier, $evidenceId, $notes) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $this->assertPendingManual($lockedPayment);

            $evidence = ManualTransferEvidence::query()
                ->whereKey($evidenceId)
                ->where('payment_id', $lockedPayment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($evidence->isReviewed()) {
                throw new PaymentTransitionConflictException('evidence_already_reviewed', 'This evidence has already been reviewed.');
            }

            $declared = $evidence->declared_amount_minor;

            if ($declared === null || $declared === $lockedPayment->amount_minor) {
                throw new PaymentValidationException(
                    'no_amount_mismatch',
                    'AMOUNT_MISMATCH_HOLD requires a declared amount differing from the payment amount.'
                );
            }

            $evidence->forceFill([
                'reviewed_by_principal_id' => $verifier->id,
                'reviewed_at' => now(),
                'review_outcome' => 'AMOUNT_MISMATCH_HOLD',
                'review_notes' => $notes,
            ])->save();

            $this->auditLogger->recordAmountMismatchHeld($lockedPayment->id, [
                'payment_ulid' => $lockedPayment->ulid,
                'verified_by_principal_id' => $verifier->id,
                'amount_minor' => $lockedPayment->amount_minor,
                'declared_amount_minor' => $declared,
            ], $verifier);

            return $evidence->fresh();
        });
    }

    private function assertPendingManual(Payment $payment): void
    {
        if ($payment->provider !== 'manual_transfer') {
            throw new PaymentValidationException(
                'not_manual_transfer',
                'Manual verification applies to Manual Bank Transfer payments only.'
            );
        }

        if ($payment->status !== 'PENDING') {
            throw new PaymentTransitionConflictException(
                'invalid_transition',
                "Payment {$payment->id} is status={$payment->status}; only PENDING may be manually verified."
            );
        }
    }

    private function invokeDonationConsequence(Payment $payment, mixed $donation, bool $succeeded, Principal $consequenceActor): void
    {
        try {
            if ($succeeded) {
                $this->donationTransitions->markSucceeded($donation, $consequenceActor);
            } else {
                $this->donationTransitions->markFailed($donation, $consequenceActor);
            }
        } catch (DonationTransitionConflictException $e) {
            $this->auditLogger->recordDonationTransitionRejected($payment->id, [
                'payment_ulid' => $payment->ulid,
                'donation_ulid' => $donation->ulid,
                'attempted_outcome' => $payment->status,
                'donation_status_observed' => $donation->fresh()->status,
            ], $consequenceActor);
        }
    }

    private function consequencePrincipal(): Principal
    {
        $catalog = SystemPrincipal::firstOrCreate(
            ['code' => 'system.payment-outcome-consequence'],
            ['description' => 'IMP-009 Payment — invokes the Donation transition surface on terminal Payment outcomes.']
        );

        return $this->principals->forSystem($catalog);
    }
}
