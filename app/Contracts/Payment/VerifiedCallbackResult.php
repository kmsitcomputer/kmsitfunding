<?php

namespace App\Contracts\Payment;

/**
 * IMP-009 — typed pass/fail result of a provider-specific signature/
 * authenticity verification (docs/implementation/
 * IMP-009-payment-hub.md "Provider Adapter Architecture" —
 * verifyCallback). Never throws a generic exception a controller would
 * need to interpret; never carries secret material.
 */
final class VerifiedCallbackResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly bool $valid,
        public readonly array $payload = [],
        public readonly ?string $providerReference = null,
        public readonly ?string $providerEventId = null,
        public readonly ?int $amountMinor = null,
        public readonly ?string $currency = null,
        public readonly string $failureReason = '',
        public readonly bool $signatureValid = false,
    ) {}

    public static function passed(
        array $payload,
        ?string $providerReference = null,
        ?string $providerEventId = null,
        ?int $amountMinor = null,
        ?string $currency = null,
    ): self {
        return new self(true, $payload, $providerReference, $providerEventId, $amountMinor, $currency, signatureValid: true);
    }

    public static function failed(string $failureReason, array $payload = [], bool $signatureValid = false): self
    {
        return new self(false, $payload, failureReason: $failureReason, signatureValid: $signatureValid);
    }
}
