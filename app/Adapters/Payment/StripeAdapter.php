<?php

namespace App\Adapters\Payment;

use App\Contracts\Payment\PaymentAdapterError;
use App\Contracts\Payment\PaymentProviderAdapter;
use App\Contracts\Payment\ProviderTransactionResult;
use App\Contracts\Payment\VerifiedCallbackResult;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentProviderCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * IMP-009 — Stripe adapter (docs/implementation/IMP-009-payment-hub.md
 * "Stripe" / "State Machines"). Webhook verification uses Stripe's
 * documented Stripe-Signature header (HMAC over the raw body +
 * timestamp, webhook signing secret distinct from the API secret key)
 * INCLUDING Stripe's own timestamp-tolerance check. The Stripe Event
 * id is the provider_event_id dedupe key.
 *
 * Stripe PaymentIntents have no native expiry — EXPIRED is reached
 * exclusively through the internal fallback sweep (HD-IMP009-09), and
 * a Stripe-side canceled maps to canonical CANCELLED, never EXPIRED.
 * The native Idempotency-Key request header is passed on creation,
 * derived from payments.idempotency_key.
 *
 * PCI boundary (hard architectural constraint): the platform NEVER
 * collects, transmits through its own backend, or stores raw card
 * PAN/CVV/expiry — Stripe Elements/Stripe.js tokenizes in the donor's
 * browser; the backend only ever sees a Stripe-generated token/
 * PaymentIntent client_secret, never raw card data.
 */
class StripeAdapter implements PaymentProviderAdapter
{
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function providerCode(): string
    {
        return 'stripe';
    }

    public function createTransaction(Payment $payment): ProviderTransactionResult
    {
        $credential = $this->credential();

        $response = Http::baseUrl($this->baseUrl($credential?->mode ?? 'SANDBOX'))
            ->timeout(30)
            ->withBasicAuth($this->secretKey($credential), '')
            ->withHeaders(['Idempotency-Key' => $payment->idempotency_key])
            ->asForm()
            ->post('/payment_intents', [
                'amount' => $payment->amount_minor,
                'currency' => strtolower($payment->currency),
                'metadata[donation_ulid]' => $payment->donation()->first()?->ulid ?? '',
                'metadata[payment_ulid]' => $payment->ulid,
            ]);

        if (! $response->successful()) {
            throw new PaymentAdapterError('provider_create_failed', 'Stripe payment intent creation failed.');
        }

        $data = $response->json();

        return new ProviderTransactionResult(
            providerReference: (string) ($data['id'] ?? ''),
            channel: isset($data['payment_method_types'][0]) ? (string) $data['payment_method_types'][0] : null,
            instructionsPayload: array_filter([
                'type' => 'stripe_payment_intent',
                'id' => $data['id'] ?? null,
                'client_secret' => $data['client_secret'] ?? null,
            ], fn ($value) => $value !== null),
            expiresAt: null,
        );
    }

    public function verifyCallback(Request $request): VerifiedCallbackResult
    {
        $rawBody = $request->getContent();
        $header = (string) $request->header('Stripe-Signature', '');

        if ($rawBody === '' || $header === '') {
            return VerifiedCallbackResult::failed('malformed_payload');
        }

        $credential = $this->credential();

        if ($credential === null) {
            return VerifiedCallbackResult::failed('invalid_signature');
        }

        $parts = [];

        foreach (explode(',', $header) as $segment) {
            $pair = explode('=', trim($segment), 2);

            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        if (! isset($parts['t'], $parts['v1'])) {
            return VerifiedCallbackResult::failed('invalid_signature');
        }

        if (abs(time() - (int) $parts['t']) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return VerifiedCallbackResult::failed('invalid_signature');
        }

        $expected = hash_hmac('sha256', $parts['t'].'.'.$rawBody, $this->webhookSecret($credential));

        if (! hash_equals($expected, $parts['v1'])) {
            return VerifiedCallbackResult::failed('invalid_signature');
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return VerifiedCallbackResult::failed('malformed_payload');
        }

        $object = is_array($payload['data'] ?? null) && is_array($payload['data']['object'] ?? null)
            ? $payload['data']['object']
            : [];

        $reference = $object['id'] ?? null;

        if (! is_string($reference) || $reference === '') {
            return VerifiedCallbackResult::failed('unknown_reference');
        }

        return VerifiedCallbackResult::passed(
            payload: $payload,
            providerReference: $reference,
            providerEventId: isset($payload['id']) ? (string) $payload['id'] : null,
            amountMinor: isset($object['amount']) ? (int) $object['amount'] : null,
            currency: isset($object['currency']) ? strtoupper((string) $object['currency']) : null,
        );
    }

    public function normalizeStatus(string $providerStatus): string
    {
        return match (strtolower($providerStatus)) {
            'requires_payment_method', 'requires_confirmation', 'processing' => 'PENDING',
            'requires_action' => 'REQUIRES_ACTION',
            'succeeded' => 'SUCCEEDED',
            'canceled' => 'CANCELLED',
            default => throw new PaymentAdapterError(
                'unknown_provider_status',
                "Stripe status '{$providerStatus}' is not mappable."
            ),
        };
    }

    public function normalizeExpiration(mixed $providerData): ?\DateTimeImmutable
    {
        return null;
    }

    public function supportedCurrencies(): array
    {
        return ['IDR', 'USD', 'EUR', 'GBP', 'JPY', 'KWD'];
    }

    public function supportsRefundCall(): bool
    {
        return true;
    }

    private function credential(): ?PaymentProviderCredential
    {
        return PaymentProviderCredential::query()->where('provider', 'stripe')->first();
    }

    private function secretKey(?PaymentProviderCredential $credential): string
    {
        if ($credential === null) {
            return '';
        }

        $decoded = json_decode(Crypt::decryptString($credential->encrypted_secret), true);

        return is_array($decoded) ? (string) ($decoded['secret_key'] ?? '') : '';
    }

    private function webhookSecret(PaymentProviderCredential $credential): string
    {
        $decoded = json_decode(Crypt::decryptString($credential->encrypted_secret), true);

        return is_array($decoded) ? (string) ($decoded['webhook_secret'] ?? '') : '';
    }

    private function baseUrl(string $mode): string
    {
        return 'https://api.stripe.com/v1';
    }
}
