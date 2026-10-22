<?php

namespace App\Contracts\Payment;

/**
 * IMP-009 — normalized provider status (docs/implementation/
 * IMP-009-payment-hub.md "Provider Adapter Architecture" —
 * retrieveStatus). The SAME normalized vocabulary a webhook produces —
 * poll and webhook paths converge on one normalization function per
 * adapter, never two.
 */
final class ProviderStatusResult
{
    public function __construct(
        public readonly string $canonicalStatus,
        public readonly ?string $providerReference = null,
        public readonly ?int $amountMinor = null,
        public readonly ?string $currency = null,
        public readonly ?\DateTimeImmutable $expiresAt = null,
    ) {}
}
