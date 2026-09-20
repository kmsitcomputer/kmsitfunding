<?php

namespace App\Adapters\Payment;

use App\Contracts\Payment\PaymentAdapterError;
use App\Contracts\Payment\PaymentProviderAdapter;
use App\Contracts\Payment\ProviderTransactionResult;
use App\Contracts\Payment\VerifiedCallbackResult;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentProviderCredential;
use App\Services\Payment\Exceptions\PaymentValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * IMP-009 — Tripay adapter (docs/implementation/IMP-009-payment-hub.md
 * "Tripay" / "State Machines"). The create-transaction request is
 * HMAC-signed with the merchant private key; the callback is verified
 * via the X-Callback-Signature header (HMAC over the raw body, SAME
 * private key) using a constant-time comparison — never plain ===.
 *
 * Tripay's API operates in whole IDR (IDR has 0 minor-unit digits per
 * CurrencyMinorUnits — the conversion is a no-op for IDR); a non-IDR
 * currency is a typed rejection at creation time, never a silent
 * substitution. Tripay's callback carries no independent event ID, so
 * the dedupe key is the stable derivation reference:status.
 *
 * The REFUND provider signal is recorded for forensics only and NEVER
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
        if ($payment->currency !== 'IDR') {
            throw new PaymentValidationException(
                'provider_currency_unsupported',
                'Tripay supports IDR payments only.'
            );
        }

        $credential = $this->credential();

        $response = Http::baseUrl($this->baseUrl($credential->mode))
            ->timeout(30)
            ->post('/transaction/create', [
                'merchant_ref' => $payment->ulid,
                'amount' => $payment->amount_minor,
                'customer_name' => 'Donor',
                'order_items' => [
                    [
                        'name' => 'Donation '.$payment->donation()->first()?->ulid,
                        'price' => $payment->amount_minor,
                        'quantity' => 1,
                    ],
                ],
                'expired_time' => $payment->expires_at?->getTimestamp(),
                'signature' => hash_hmac('sha256', $payment->ulid.$payment->amount_minor, $this->privateKey($credential)),
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

        $expected = hash_hmac('sha256', $rawBody, $this->privateKey($credential));

        if (! hash_equals($expected, $signature)) {
            return VerifiedCallbackResult::failed('invalid_signature');
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return VerifiedCallbackResult::failed('malformed_payload');
        }

        $reference = $payload['reference'] ?? null;
        $merchantRef = $payload['merchant_ref'] ?? null;

        if (! is_string($reference) || $reference === '') {
            return VerifiedCallbackResult::failed('unknown_reference');
        }

        $status = strtoupper((string) ($payload['status'] ?? ''));

        return VerifiedCallbackResult::passed(
            payload: $payload,
            providerReference: $reference,
            providerEventId: $reference.':'.($merchantRef ?? '').':'.$status,
            amountMinor: isset($payload['amount']) ? (int) $payload['amount'] : null,
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

    private function privateKey(PaymentProviderCredential $credential): string
    {
        $decoded = json_decode(Crypt::decryptString($credential->encrypted_secret), true);

        return is_array($decoded) ? (string) ($decoded['private_key'] ?? '') : '';
    }

    private function baseUrl(string $mode): string
    {
        return $mode === 'PRODUCTION' ? 'https://tripay.co.id/api' : 'https://tripay.co.id/api-sandbox';
    }
}
