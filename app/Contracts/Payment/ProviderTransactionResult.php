<?php

namespace App\Contracts\Payment;

/**
 * IMP-009 — normalized result of a provider's transaction/invoice/
 * payment-intent creation call (docs/implementation/
 * IMP-009-payment-hub.md "Provider Adapter Architecture" —
 * createTransaction). Adapter-normalized, bounded presentation/
 * instruction data only — never a raw dump of the provider's API
 * response, never a provider secret/credential or card data.
 */
final class ProviderTransactionResult
{
    /**
     * @param  array<string, mixed>|null  $instructionsPayload
     */
    public function __construct(
        public readonly string $providerReference,
        public readonly ?string $channel = null,
        public readonly ?array $instructionsPayload = null,
        public readonly ?\DateTimeImmutable $expiresAt = null,
    ) {}
}
