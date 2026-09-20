<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentAdapterError;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentProviderEvent;
use App\Models\Rbac\IntegrationPrincipal;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Donation\DonationTransitionService;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Rbac\PrincipalService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * IMP-009 — canonical webhook/callback processing pipeline
 * (docs/implementation/IMP-009-payment-hub.md "Webhook Security",
 * steps 1-13, identical shape for tripay/xendit/stripe — Manual
 * Transfer has no webhook):
 *
 * receive -> provider identification (route, never payload shape) ->
 * cryptographic verification (BEFORE any business mutation) -> payload
 * validation -> Payment resolution (never create from a webhook) ->
 * amount/currency validation -> duplicate detection -> lock (fixed
 * Donation-then-Payment order) -> transition validation -> persist ->
 * audit -> authorized downstream consequence -> acknowledge (only after
 * the transaction commits).
 *
 * Verification failure stops the pipeline: the event is recorded with
 * payment_id NULL where unresolvable, no state changes, a generic
 * non-revealing outcome returned. No sensitive diagnostic (raw
 * provider error, exception message, stack trace, secret fragment) is
 * ever returned in any response — minimal generic outcomes only.
 *
 * Terminal SUCCEEDED/FAILED outcomes record the Payment fact first,
 * then REQUEST the Donation transition through the existing
 * System-Principal-gated surface (HD-IMP009-11) in the SAME
 * transaction — invoked with the system.payment-outcome-consequence
 * System Principal, deliberately DISTINCT from the webhook's own
 * Integration Principal (separation-of-duties). A rejected Donation
 * transition (already-terminal Donation) is caught and recorded via
 * payment.donation_transition_rejected (CRITICAL) for mandatory
 * review — never forced, reopened, or worked around (HD-IMP009-02).
 */
class PaymentWebhookHandler
{
    public const OUTCOME_PROCESSED = 'processed';

    public const OUTCOME_REJECTED = 'rejected';

    public const OUTCOME_DUPLICATE = 'duplicate';

    public function __construct(
        private readonly PaymentAdapterFactory $adapters,
        private readonly PaymentTransitionService $transitions,
        private readonly DonationTransitionService $donationTransitions,
        private readonly PaymentAuditLogger $auditLogger,
        private readonly PrincipalService $principals,
    ) {}

    /**
     * @return array{outcome: string, http_status: int}
     */
    public function handle(string $provider, Request $request): array
    {
        $adapter = $this->adapters->for($provider);
        $integrationActor = $this->integrationPrincipal($provider);

        $verified = $adapter->verifyCallback($request);

        if (! $verified->valid) {
            $this->recordRejection($provider, null, $verified->failureReason, $request->getContent(), $integrationActor);

            return ['outcome' => self::OUTCOME_REJECTED, 'http_status' => $this->rejectionStatus($provider)];
        }

        $payment = $verified->providerReference !== null
            ? Payment::query()->where('provider', $provider)->where('provider_reference', $verified->providerReference)->first()
            : null;

        if ($payment === null) {
            $this->recordRejection($provider, null, 'unknown_reference', $request->getContent(), $integrationActor);

            return ['outcome' => self::OUTCOME_REJECTED, 'http_status' => $this->rejectionStatus($provider)];
        }

        if ($verified->amountMinor !== null && $verified->amountMinor !== $payment->amount_minor) {
            $this->recordRejection($provider, $payment->id, 'amount_mismatch', $request->getContent(), $integrationActor);

            return ['outcome' => self::OUTCOME_REJECTED, 'http_status' => $this->rejectionStatus($provider)];
        }

        if ($verified->currency !== null && strtoupper($verified->currency) !== strtoupper($payment->currency)) {
            $this->recordRejection($provider, $payment->id, 'currency_mismatch', $request->getContent(), $integrationActor);

            return ['outcome' => self::OUTCOME_REJECTED, 'http_status' => $this->rejectionStatus($provider)];
        }

        $eventId = $verified->providerEventId;

        if ($eventId !== null && PaymentProviderEvent::query()
            ->where('provider', $provider)
            ->where('provider_event_id', $eventId)
            ->exists()) {
            // The (provider, provider_event_id) composite-unique row
            // already exists: this is the expected at-least-once
            // redelivery, not a rejection. The replay itself is recorded
            // for forensics with a NULL provider_event_id (NULL-distinct
            // unique semantics admit unlimited such rows) and DUPLICATE
            // outcome; no Payment/Donation state changes.
            $this->recordEvent($provider, $payment->id, null, 'webhook', true, 'DUPLICATE', $request->getContent());
            $this->auditLogger->recordWebhookReceived(null, [
                'provider' => $provider,
                'processing_result' => 'DUPLICATE',
            ], $integrationActor);

            return ['outcome' => self::OUTCOME_DUPLICATE, 'http_status' => 200];
        }

        try {
            $canonical = $adapter->normalizeStatus($this->providerStatusFrom($verified->payload, $provider));
        } catch (PaymentAdapterError) {
            $this->recordRejection($provider, $payment->id, 'malformed_payload', $request->getContent(), $integrationActor);

            return ['outcome' => self::OUTCOME_REJECTED, 'http_status' => $this->rejectionStatus($provider)];
        }

        $consequenceActor = $this->systemPrincipal('system.payment-outcome-consequence');

        try {
            DB::transaction(function () use ($provider, $payment, $canonical, $eventId, $request, $integrationActor, $consequenceActor, $verified) {
                $lockedDonation = $payment->donation()->lockForUpdate()->firstOrFail();
                $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

                if ($lockedPayment->status === $canonical) {
                    $this->recordEvent($provider, $lockedPayment->id, $eventId, 'webhook', true, 'DUPLICATE', $request->getContent());

                    return;
                }

                if ($lockedPayment->isTerminal()) {
                    $this->recordEvent($provider, $lockedPayment->id, $eventId, 'webhook', true, 'ACCEPTED', $request->getContent());

                    return;
                }

                // Out-of-order earlier-stage signal (e.g. a "pending" webhook
                // arriving after the Payment already advanced): recorded and
                // ignored for state-transition purposes — status never moves
                // backward.
                if ($canonical === 'PENDING' && $lockedPayment->status !== 'PENDING') {
                    $this->recordEvent($provider, $lockedPayment->id, $eventId, 'webhook', true, 'ACCEPTED', $request->getContent());

                    return;
                }

                $this->transitions->applyCanonicalOutcome($lockedPayment, $canonical, $integrationActor, [
                    'provider_reference' => $verified->providerReference,
                ]);

                $this->recordEvent($provider, $lockedPayment->id, $eventId, 'webhook', true, 'ACCEPTED', $request->getContent());
                $this->auditLogger->recordWebhookReceived(null, [
                    'provider' => $provider,
                    'processing_result' => 'ACCEPTED',
                ], $integrationActor);

                $fresh = $lockedPayment->fresh();

                if (in_array($fresh->status, ['SUCCEEDED', 'FAILED'], true)) {
                    $this->invokeDonationConsequence($fresh, $lockedDonation, $consequenceActor);
                }
            });
        } catch (QueryException $e) {
            // Genuine concurrent redelivery: both transactions passed the
            // pre-check, the loser blocked on the composite-unique index
            // until the winner committed. The loser's insert collided —
            // that IS the duplicate outcome, not an error: record the
            // replay for forensics (NULL event id, never colliding) and
            // report DUPLICATE with no state change.
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            $this->recordEvent($provider, $payment->id, null, 'webhook', true, 'DUPLICATE', $request->getContent());
            $this->auditLogger->recordWebhookReceived(null, [
                'provider' => $provider,
                'processing_result' => 'DUPLICATE',
            ], $integrationActor);

            return ['outcome' => self::OUTCOME_DUPLICATE, 'http_status' => 200];
        }

        return ['outcome' => self::OUTCOME_PROCESSED, 'http_status' => 200];
    }

    private function invokeDonationConsequence(Payment $payment, mixed $donation, Principal $consequenceActor): void
    {
        try {
            if ($payment->status === 'SUCCEEDED') {
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

    private function providerStatusFrom(array $payload, string $provider): string
    {
        return match ($provider) {
            'tripay' => (string) ($payload['status'] ?? ''),
            'xendit' => (string) ($payload['status'] ?? ''),
            // Stripe delivers an Event envelope: the PaymentIntent's own
            // status lives at data.object.status — the envelope type
            // (payment_intent.succeeded) is not itself the status.
            'stripe' => (string) ($payload['data']['object']['status'] ?? ''),
            default => '',
        };
    }

    private function recordRejection(string $provider, ?int $paymentId, string $reason, string $rawBody, Principal $actor): void
    {
        $result = match ($reason) {
            'invalid_signature' => 'REJECTED_INVALID_SIGNATURE',
            'unknown_reference' => 'REJECTED_UNKNOWN_REFERENCE',
            'amount_mismatch' => 'REJECTED_AMOUNT_MISMATCH',
            'currency_mismatch' => 'REJECTED_CURRENCY_MISMATCH',
            default => 'REJECTED_MALFORMED',
        };

        $this->recordEvent($provider, $paymentId, null, 'webhook', false, $result, $rawBody);
        $this->auditLogger->recordWebhookVerificationFailed([
            'provider' => $provider,
            'reason' => $result,
        ], $actor);
    }

    private function recordEvent(
        string $provider,
        ?int $paymentId,
        ?string $providerEventId,
        string $eventType,
        bool $signatureValid,
        string $processingResult,
        string $rawBody,
    ): void {
        $event = new PaymentProviderEvent;
        $event->forceFill([
            'payment_id' => $paymentId,
            'provider' => $provider,
            'provider_event_id' => $providerEventId,
            'event_type' => $eventType,
            'signature_valid' => $signatureValid,
            'processing_result' => $processingResult,
            'raw_payload_ciphertext' => $rawBody === '' ? null : Crypt::encryptString($rawBody),
            'received_at' => now(),
        ]);
        $event->save();
    }

    private function rejectionStatus(string $provider): int
    {
        return match ($provider) {
            'tripay' => 400,
            'xendit' => 400,
            'stripe' => 400,
            default => 400,
        };
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $driverCode = $e->errorInfo[1] ?? null;

        return in_array($driverCode, [1062, 19, 2067], true);
    }

    private function integrationPrincipal(string $provider): Principal
    {
        $catalog = IntegrationPrincipal::firstOrCreate(
            ['code' => "payment.{$provider}-webhook"],
            ['description' => "IMP-009 Payment — {$provider} webhook attribution."]
        );

        return $this->principals->forIntegration($catalog);
    }

    private function systemPrincipal(string $code): Principal
    {
        $catalog = SystemPrincipal::firstOrCreate(
            ['code' => $code],
            ['description' => "IMP-009 Payment — {$code}."]
        );

        return $this->principals->forSystem($catalog);
    }
}
