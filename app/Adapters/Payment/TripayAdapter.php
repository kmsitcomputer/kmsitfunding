<?php

namespace App\Adapters\Payment;

use App\Contracts\Payment\PaymentAdapterError;
use App\Contracts\Payment\PaymentProviderAdapter;
use App\Contracts\Payment\ProviderTransactionResult;
use App\Contracts\Payment\VerifiedCallbackResult;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentProviderCredential;
use App\Services\Payment\Exceptions\PaymentValidationException;
use App\Services\Payment\ProviderCredentialPayload;
use App\Support\Money\Exceptions\UnknownCurrencyException;
use App\Support\Money\ProviderAmountConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * IMP-009 — Tripay adapter (docs/implementation/IMP-009-payment-hub.md
 * "Tripay" / "State Machines").
 *
 * Provider-unit contract (HD-IMP009-13, verified against Tripay's
 * official API developer guide, https://tripay.co.id/developer,
 * 2026-09-20): the create-transaction `amount` and the callback
 * `total_amount` are whole-IDR integers (e.g. 1000000 for
 * Rp 1.000.000 — Tripay denominates in rupiah, never sen). Canonical
 * amount_minor (IDR digits = 2) is converted explicitly through
 * ProviderAmountConverter on the way IN and OUT — never compared
 * cross-unit.
 *
 * Official request shape: method (channel code), merchant_ref,
 * amount, customer_name, customer_email, order_items
 * (name/price/quantity), expired_time, signature =
 * HMAC-SHA256(merchantCode.merchantRef.amount, privateKey), with
 * `Authorization: Bearer {api_key}`. The callback is verified via the
 * X-Callback-Signature header (HMAC over the raw body, SAME private
 * key) using a constant-time comparison — never plain ===.
 *
 * Tripay's callback carries no independent event ID, so the dedupe
 * key is the stable derivation reference:merchant_ref:status. The
 * REFUND provider signal is recorded for forensics only and NEVER
 * mapped to a canonical Payment state (IMP-016's concern).
 */
class TripayAdapter implements PaymentProviderAdapter
{
    public function providerCode(): string
    {
        return 'tripay';
    }

    public function createTransaction(Payment $payment): ProviderTransactionResult
    {
        $credential = $this->credential();
        $fields = ProviderCredentialPayload::decode('tripay', $credential);

        if ($payment->channel === null || trim($payment->channel) === '') {
            throw new PaymentValidationException(
                'channel_required',
                'Tripay requires a payment channel code.'
            );
        }

        $amount = ProviderAmountConverter::toProviderUnits($payment->amount_minor, $payment->currency, 'tripay');

        $merchantRef = $payment->ulid;
        $donation = $payment->donation()->first();

        $response = Http::baseUrl($this->baseUrl($credential->mode))
            ->timeout(30)
            ->withToken($fields['api_key'])
            ->post('/transaction/create', [
                'method' => $payment->channel,
                'merchant_ref' => $merchantRef,
                'amount' => $amount,
                'customer_name' => $this->customerName($payment),
                'customer_email' => $this->customerEmail($payment),
                'order_items' => [
                    [
                        'name' => 'Donation '.$donation?->ulid,
                        'price' => $amount,
                        'quantity' => 1,
                    ],
                ],
                'expired_time' => $payment->expires_at?->getTimestamp(),
                'signature' => hash_hmac('sha256', $fields['merchant_code'].$merchantRef.$amount, $fields['private_key']),
            ]);

        if (! $response->successful()) {
            throw new PaymentAdapterError('provider_create_failed', 'Tripay transaction creation failed.');
        }

        $data = $response->json('data', []);

        return new ProviderTransactionResult(
            providerReference: (string) ($data['reference'] ?? ''),
            channel: isset($data['payment_method']) ? (string) $data['payment_method'] : null,
            instructionsPayload: array_filter([
                'type' => 'tripay_transaction',
                'reference' => $data['reference'] ?? null,
                'pay_url' => $data['pay_url'] ?? null,
                'amount' => $data['amount'] ?? null,
            ], fn ($value) => $value !== null),
            expiresAt: isset($data['expired_time']) ? new \DateTimeImmutable('@'.(int) $data['expired_time']) : null,
        );
    }

    public function verifyCallback(Request $request): VerifiedCallbackResult
    {
        $rawBody = $request->getContent();
        $signature = (string) $request->header('X-Callback-Signature', '');

        if ($rawBody === '' || $signature === '') {
            return VerifiedCallbackResult::failed('malformed_payload');
        }

        $credential = $this->credential();

        if ($credential === null) {
            return VerifiedCallbackResult::failed('invalid_signature');
        }

        try {
            $fields = ProviderCredentialPayload::decode('tripay', $credential);
        } catch (PaymentAdapterError) {
            return VerifiedCallbackResult::failed('invalid_signature');
        }

        $expected = hash_hmac('sha256', $rawBody, $fields['private_key']);

        if (! hash_equals($expected, $signature)) {
            return VerifiedCallbackResult::failed('invalid_signature');
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return VerifiedCallbackResult::failed('malformed_payload', signatureValid: true);
        }

        $reference = $payload['reference'] ?? null;
        $merchantRef = $payload['merchant_ref'] ?? null;

        if (! is_string($reference) || $reference === '') {
            return VerifiedCallbackResult::failed('unknown_reference', signatureValid: true);
        }

        $status = strtoupper((string) ($payload['status'] ?? ''));

        $amountMinor = null;

        if (isset($payload['total_amount'])) {
            try {
                $amountMinor = ProviderAmountConverter::toCanonicalMinor((int) $payload['total_amount'], 'IDR', 'tripay');
            } catch (PaymentValidationException|UnknownCurrencyException) {
                return VerifiedCallbackResult::failed('malformed_payload', signatureValid: true);
            }
        }

        return VerifiedCallbackResult::passed(
            payload: $payload,
            providerReference: $reference,
            providerEventId: $reference.':'.($merchantRef ?? '').':'.$status,
            amountMinor: $amountMinor,
            currency: 'IDR',
        );
    }

    public function normalizeStatus(string $providerStatus): string
    {
        return match (strtoupper($providerStatus)) {
            'UNPAID' => 'PENDING',
            'PAID' => 'SUCCEEDED',
            'EXPIRED' => 'EXPIRED',
            'FAILED' => 'FAILED',
            default => throw new PaymentAdapterError(
                'unknown_provider_status',
                "Tripay status '{$providerStatus}' is not mappable (REFUND is never mapped — IMP-016's concern)."
            ),
        };
    }

    public function normalizeExpiration(mixed $providerData): ?\DateTimeImmutable
    {
        $timestamp = is_array($providerData) ? ($providerData['expired_time'] ?? null) : null;

        if ($timestamp === null) {
            return null;
        }

        return new \DateTimeImmutable('@'.(int) $timestamp);
    }

    public function supportedCurrencies(): array
    {
        return ['IDR'];
    }

    public function supportsRefundCall(): bool
    {
        return true;
    }

    private function credential(): ?PaymentProviderCredential
    {
        return PaymentProviderCredential::query()->where('provider', 'tripay')->first();
    }

    private function customerName(Payment $payment): string
    {
        $donation = $payment->donation()->first();

        return $donation?->guest_name ?? 'Donor';
    }

    private function customerEmail(Payment $payment): string
    {
        $donation = $payment->donation()->first();

        return $donation?->guest_email ?? $donation?->donor?->humanUser?->email ?? '';
    }

    private function baseUrl(string $mode): string
    {
        return $mode === 'PRODUCTION' ? 'https://tripay.co.id/api' : 'https://tripay.co.id/api-sandbox';
    }
}
