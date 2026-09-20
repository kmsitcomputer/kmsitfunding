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
 * IMP-009 — Xendit adapter, PaymentRequest API baseline
 * (docs/implementation/IMP-009-payment-hub.md "Xendit" / "State
 * Machines", HD-IMP009-05 FINAL / LOCKED — NOT the legacy Invoice
 * API). Xendit-specific concepts (Payment Request, Payment Method,
 * Payment Session, capture semantics) remain isolated here — no
 * Xendit vocabulary leaks into the canonical state machine.
 *
 * Webhooks are verified via the x-callback-token header compared
 * (constant-time) against the configured callback verification token
 * — a second secret distinct from the API key, never a
 * request-supplied value trusted at face value. The webhook payload's
 * own event/notification id is the provider_event_id dedupe key.
 *
 * Exact endpoint/version/field shapes MUST be verified against
 * Xendit's current authoritative documentation at integration time
 * (HD-IMP009-05 "External Verification") — the mapping table below is
 * the PaymentRequest API's documented status vocabulary, implemented
 * as an explicit allow-list, never a bare pass-through.
 */
class XenditAdapter implements PaymentProviderAdapter
{
    public function providerCode(): string
    {
        return 'xendit';
    }

    public function createTransaction(Payment $payment): ProviderTransactionResult
    {
        $credential = $this->credential();

        $response = Http::baseUrl($this->baseUrl($credential?->mode ?? 'SANDBOX'))
            ->timeout(30)
            ->withBasicAuth($this->apiKey($credential), '')
            ->post('/payment_requests', [
                'external_id' => $payment->ulid,
                'amount' => $payment->amount_minor,
                'currency' => $payment->currency,
                'payment_method' => ['type' => $payment->channel ?? 'VIRTUAL_ACCOUNT'],
            ]);

        if (! $response->successful()) {
            throw new PaymentAdapterError('provider_create_failed', 'Xendit payment request creation failed.');
        }

        $data = $response->json();

        return new ProviderTransactionResult(
            providerReference: (string) ($data['id'] ?? ''),
            channel: isset($data['payment_method']['type']) ? (string) $data['payment_method']['type'] : null,
            instructionsPayload: array_filter([
                'type' => 'xendit_payment_request',
                'id' => $data['id'] ?? null,
                'status' => $data['status'] ?? null,
            ], fn ($value) => $value !== null),
            expiresAt: isset($data['expires_at']) ? new \DateTimeImmutable((string) $data['expires_at']) : null,
        );
    }

    public function verifyCallback(Request $request): VerifiedCallbackResult
    {
        $token = (string) $request->header('x-callback-token', '');
        $rawBody = $request->getContent();

        if ($rawBody === '' || $token === '') {
            return VerifiedCallbackResult::failed('malformed_payload');
        }

        $credential = $this->credential();

        if ($credential === null) {
            return VerifiedCallbackResult::failed('invalid_signature');
        }

        if (! hash_equals($this->callbackToken($credential), $token)) {
            return VerifiedCallbackResult::failed('invalid_signature');
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return VerifiedCallbackResult::failed('malformed_payload');
        }

        $reference = $payload['id'] ?? $payload['payment_request_id'] ?? null;

        if (! is_string($reference) || $reference === '') {
            return VerifiedCallbackResult::failed('unknown_reference');
        }

        return VerifiedCallbackResult::passed(
            payload: $payload,
            providerReference: $reference,
            providerEventId: isset($payload['event_id']) ? (string) $payload['event_id'] : null,
            amountMinor: isset($payload['amount']) ? (int) $payload['amount'] : null,
            currency: isset($payload['currency']) ? (string) $payload['currency'] : null,
        );
    }

    public function normalizeStatus(string $providerStatus): string
    {
        return match (strtoupper($providerStatus)) {
            'PENDING' => 'PENDING',
            'REQUIRES_ACTION' => 'REQUIRES_ACTION',
            'SUCCEEDED' => 'SUCCEEDED',
            'FAILED' => 'FAILED',
            'EXPIRED' => 'EXPIRED',
            'CANCELED' => 'CANCELLED',
            default => throw new PaymentAdapterError(
                'unknown_provider_status',
                "Xendit status '{$providerStatus}' is not mappable."
            ),
        };
    }

    public function normalizeExpiration(mixed $providerData): ?\DateTimeImmutable
    {
        $expiry = is_array($providerData) ? ($providerData['expires_at'] ?? null) : null;

        if (! is_string($expiry) || $expiry === '') {
            return null;
        }

        return new \DateTimeImmutable($expiry);
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
        return PaymentProviderCredential::query()->where('provider', 'xendit')->first();
    }

    private function apiKey(?PaymentProviderCredential $credential): string
    {
        if ($credential === null) {
            return '';
        }

        $decoded = json_decode(Crypt::decryptString($credential->encrypted_secret), true);

        return is_array($decoded) ? (string) ($decoded['api_key'] ?? '') : '';
    }

    private function callbackToken(PaymentProviderCredential $credential): string
    {
        $decoded = json_decode(Crypt::decryptString($credential->encrypted_secret), true);

        return is_array($decoded) ? (string) ($decoded['callback_token'] ?? '') : '';
    }

    private function baseUrl(string $mode): string
    {
        return $mode === 'PRODUCTION' ? 'https://api.xendit.co' : 'https://api.xendit.co';
    }
}
