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
 * IMP-009 — Xendit adapter, PaymentRequest API baseline
 * (docs/implementation/IMP-009-payment-hub.md "Xendit" / "State
 * Machines", HD-IMP009-05 FINAL / LOCKED — NOT the legacy Invoice
 * API). Xendit-specific concepts remain isolated here — no Xendit
 * vocabulary leaks into the canonical state machine.
 *
 * Provider-unit contract (HD-IMP009-13, verified against Xendit's
 * official PaymentRequest documentation, https://docs.xendit.co
 * "Create a payment request" + "Payment webhook notification",
 * 2026-09-20): `request_amount` is denominated in MAJOR units (e.g.
 * 100000 for Rp 100.000) on BOTH the create call and the webhook
 * `data.request_amount`. Canonical amount_minor is converted
 * explicitly through ProviderAmountConverter on the way IN and OUT —
 * never compared cross-unit.
 *
 * Official create shape: POST /v3/payment_requests with reference_id
 * (merchant reference), type=PAY, currency, request_amount,
 * channel_code, capture_method=AUTOMATIC; HTTP basic auth with the
 * secret key. The webhook envelope is
 * {event, business_id, created, data: {payment_request_id,
 * reference_id, currency, request_amount, status, ...}} verified via
 * the x-callback-token header (constant-time) against the configured
 * callback verification token — a second secret distinct from the
 * API key, never a request-supplied value trusted at face value.
 *
 * The official webhook envelope carries no provider-native event id,
 * so the dedupe key is the stable derivation
 * payment_request_id:status:first-capture-id (documented here, same
 * acknowledged-narrower-defense posture as Tripay).
 *
 * Exact endpoint/version/field shapes were verified against Xendit's
 * current authoritative documentation at implementation time
 * (HD-IMP009-05 "External Verification").
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
        $fields = ProviderCredentialPayload::decode('xendit', $credential);

        if ($payment->channel === null || trim($payment->channel) === '') {
            throw new PaymentValidationException(
                'channel_required',
                'Xendit requires a payment channel code.'
            );
        }

        $requestAmount = ProviderAmountConverter::toProviderUnits($payment->amount_minor, $payment->currency, 'xendit');

        $response = Http::baseUrl($this->baseUrl($credential->mode))
            ->timeout(30)
            ->withBasicAuth($fields['api_key'], '')
            ->post('/v3/payment_requests', [
                'reference_id' => $payment->ulid,
                'type' => 'PAY',
                'currency' => $payment->currency,
                'request_amount' => $requestAmount,
                'channel_code' => $payment->channel,
                'capture_method' => 'AUTOMATIC',
            ]);

        if (! $response->successful()) {
            throw new PaymentAdapterError('provider_create_failed', 'Xendit payment request creation failed.');
        }

        $data = $response->json();

        return new ProviderTransactionResult(
            providerReference: (string) ($data['payment_request_id'] ?? $data['id'] ?? ''),
            channel: isset($data['channel_code']) ? (string) $data['channel_code'] : null,
            instructionsPayload: array_filter([
                'type' => 'xendit_payment_request',
                'id' => $data['payment_request_id'] ?? $data['id'] ?? null,
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

        try {
            $fields = ProviderCredentialPayload::decode('xendit', $credential);
        } catch (PaymentAdapterError) {
            return VerifiedCallbackResult::failed('invalid_signature');
        }

        if (! hash_equals($fields['callback_token'], $token)) {
            return VerifiedCallbackResult::failed('invalid_signature');
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return VerifiedCallbackResult::failed('malformed_payload', signatureValid: true);
        }

        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            return VerifiedCallbackResult::failed('malformed_payload', signatureValid: true);
        }

        $reference = $data['payment_request_id'] ?? $data['reference_id'] ?? null;

        if (! is_string($reference) || $reference === '') {
            return VerifiedCallbackResult::failed('unknown_reference', signatureValid: true);
        }

        $status = strtoupper((string) ($data['status'] ?? ''));
        $currency = isset($data['currency']) ? strtoupper((string) $data['currency']) : null;

        $amountMinor = null;

        if (isset($data['request_amount'])) {
            if (! is_numeric($data['request_amount']) || $currency === null || $currency === '') {
                return VerifiedCallbackResult::failed('malformed_payload', signatureValid: true);
            }

            try {
                $amountMinor = ProviderAmountConverter::toCanonicalMinor((int) $data['request_amount'], $currency, 'xendit');
            } catch (PaymentValidationException|UnknownCurrencyException) {
                return VerifiedCallbackResult::failed('malformed_payload', signatureValid: true);
            }
        }

        $captureId = $data['captures'][0]['capture_id'] ?? '';

        return VerifiedCallbackResult::passed(
            payload: $payload,
            providerReference: $reference,
            providerEventId: $reference.':'.$status.':'.(is_string($captureId) ? $captureId : ''),
            amountMinor: $amountMinor,
            currency: $currency,
        );
    }

    public function normalizeStatus(string $providerStatus): string
    {
        return match (strtoupper($providerStatus)) {
            'PENDING', 'AUTHORIZED' => 'PENDING',
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

    private function baseUrl(string $mode): string
    {
        return 'https://api.xendit.co';
    }
}
