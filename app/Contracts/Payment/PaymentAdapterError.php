<?php

namespace App\Contracts\Payment;

/**
 * IMP-009 — typed, non-sensitive provider error normalization
 * (docs/implementation/IMP-009-payment-hub.md "Provider Adapter
 * Architecture" — normalizeError). A raw provider exception/response
 * body is NEVER surfaced to a donor-facing response; it is recorded
 * (encrypted) in payment_provider_events only.
 */
final class PaymentAdapterError extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
