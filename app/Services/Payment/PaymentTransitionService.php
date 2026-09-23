<?php

namespace App\Services\Payment;

use App\Models\Payment\Payment;
use App\Models\Rbac\Principal;
use App\Services\Payment\Exceptions\PaymentTransitionConflictException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-009 — the ONLY writer of Payment lifecycle status
 * (docs/implementation/IMP-009-payment-hub.md "State Machines" / BR-6,
 * mirroring DonationTransitionService's exact concurrency idiom). Every
 * transition locks the Payment row first and re-verifies its current
 * status under that lock — two concurrent attempts on the same Payment
 * MUST NOT both succeed; the loser observes the terminal state and is
 * rejected with a typed conflict.
 *
 * WHO may call what is a Policy-layer concern (PaymentPolicy), never
 * decided here: succeed/fail/expire are reserved for an authorized
 * Integration/System Principal invocation (webhook/poll/sweep); cancel
 * is the donor/admin PENDING/REQUIRES_ACTION-path transition. A
 * terminal Payment is never mutated back (BR-6).
 *
 * Duplicate-event behavior: reporting the SAME terminal outcome the
 * Payment already recorded is a no-op returning the unchanged Payment
 * (the caller records processing_result = DUPLICATE at the event
 * layer). Out-of-order earlier-stage signals never move status
 * backward — also a no-op here.
 */
class PaymentTransitionService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED = [
        'PENDING' => ['REQUIRES_ACTION', 'SUCCEEDED', 'FAILED', 'EXPIRED', 'CANCELLED'],
        'REQUIRES_ACTION' => ['SUCCEEDED', 'FAILED', 'EXPIRED', 'CANCELLED'],
    ];

    public function __construct(private readonly PaymentAuditLogger $auditLogger) {}

    public function markRequiresAction(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $locked = $this->lockedTransitionable($payment, 'REQUIRES_ACTION');

            $locked->forceFill(['status' => 'REQUIRES_ACTION'])->save();

            return $locked;
        });
    }

    public function markSucceeded(Payment $payment, Principal $actor, ?string $providerReference = null): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $providerReference) {
            $locked = $this->lockedTransitionable($payment, 'SUCCEEDED');

            $locked->forceFill(array_filter([
                'status' => 'SUCCEEDED',
                'succeeded_at' => now(),
                'provider_reference' => $providerReference ?? $locked->provider_reference,
            ], fn ($value) => $value !== null))->save();

            $this->auditLogger->recordSucceeded($locked->id, [
                'payment_ulid' => $locked->ulid,
                'donation_ulid' => $locked->donation()->first()->ulid,
                'provider' => $locked->provider,
                'provider_reference' => (string) $locked->provider_reference,
                'amount_minor' => $locked->amount_minor,
                'currency' => $locked->currency,
            ], $actor);

            return $locked;
        });
    }

    public function markFailed(Payment $payment, Principal $actor, ?string $failureReason = null): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $failureReason) {
            $locked = $this->lockedTransitionable($payment, 'FAILED');

            $locked->forceFill([
                'status' => 'FAILED',
                'failed_at' => now(),
                'failure_reason' => $failureReason ?? $locked->failure_reason,
            ])->save();

            $this->auditLogger->recordFailed($locked->id, [
                'payment_ulid' => $locked->ulid,
                'donation_ulid' => $locked->donation()->first()->ulid,
                'provider' => $locked->provider,
                'provider_reference' => (string) $locked->provider_reference,
                'amount_minor' => $locked->amount_minor,
                'currency' => $locked->currency,
            ], $actor);

            return $locked;
        });
    }

    public function markExpired(Payment $payment, Principal $actor): Payment
    {
        return DB::transaction(function () use ($payment, $actor) {
            $locked = $this->lockedTransitionable($payment, 'EXPIRED');

            $locked->forceFill([
                'status' => 'EXPIRED',
                'expired_at' => now(),
                'failure_reason' => $locked->failure_reason ?? 'EXPIRED_NO_ACTION',
            ])->save();

            $this->auditLogger->recordExpired($locked->id, [
                'payment_ulid' => $locked->ulid,
            ], $actor);

            return $locked;
        });
    }

    public function cancel(Payment $payment, Principal $actor): Payment
    {
        return DB::transaction(function () use ($payment, $actor) {
            $locked = $this->lockedTransitionable($payment, 'CANCELLED');

            $locked->forceFill([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
                'cancelled_by_principal_id' => $actor->id,
            ])->save();

            $this->auditLogger->recordCancelled($locked->id, [
                'payment_ulid' => $locked->ulid,
                'cancelled_by_principal_id' => $actor->id,
            ], $actor);

            return $locked;
        });
    }

    /**
     * A duplicate terminal report for the SAME outcome already recorded
     * is a legitimate no-op (the caller records DUPLICATE at the event
     * layer); any other transition off a terminal row is a typed
     * conflict. An out-of-order earlier-stage signal against a later
     * status is likewise a no-op — status never moves backward.
     */
    public function applyCanonicalOutcome(Payment $payment, string $canonicalStatus, Principal $actor, array $context = []): Payment
    {
        // Owns the transaction so the lock-check-act sequence below is
        // atomic: the inner mark*() calls nest via savepoints (Laravel
        // default) and re-verify under the same held row lock. Callers
        // with a wider transaction (webhook handler: Payment write +
        // Donation invocation + audit in one commit) nest this whole call
        // inside their own transaction instead — same savepoint nesting,
        // one outer commit.
        return DB::transaction(function () use ($payment, $canonicalStatus, $actor, $context) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === $canonicalStatus) {
                return $locked;
            }

            if ($locked->isTerminal()) {
                throw new PaymentTransitionConflictException(
                    'invalid_transition',
                    "Payment {$locked->id} is status={$locked->status}; terminal states never transition."
                );
            }

            return match ($canonicalStatus) {
                'REQUIRES_ACTION' => $this->markRequiresAction($locked),
                'SUCCEEDED' => $this->markSucceeded($locked, $actor, $context['provider_reference'] ?? null),
                'FAILED' => $this->markFailed($locked, $actor, $context['failure_reason'] ?? null),
                'EXPIRED' => $this->markExpired($locked, $actor),
                'CANCELLED' => $this->cancel($locked, $actor),
                default => throw new PaymentTransitionConflictException(
                    'unknown_canonical_status',
                    "Unknown canonical Payment status '{$canonicalStatus}'."
                ),
            };
        });
    }

    private function lockedTransitionable(Payment $payment, string $target): Payment
    {
        $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

        if (! in_array($target, self::ALLOWED[$locked->status] ?? [], true)) {
            throw new PaymentTransitionConflictException(
                'invalid_transition',
                "Payment {$locked->id} is status={$locked->status}; transition to {$target} is not permitted."
            );
        }

        return $locked;
    }
}
