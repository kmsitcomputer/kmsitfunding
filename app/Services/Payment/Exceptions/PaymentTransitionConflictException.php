<?php

namespace App\Services\Payment\Exceptions;

/**
 * A Payment lifecycle transition or idempotency replay was attempted
 * against a Payment no longer (or not yet) in the required state, an
 * idempotency key was reused with a materially different payload, or a
 * concurrent creation collided with HD-IMP009-01's one-ACTIVE-attempt
 * invariant (docs/implementation/IMP-009-payment-hub.md BR-6/BR-19 /
 * "Idempotency" / "Failure Semantics"). Classified so callers can map to
 * a 409 without string-matching the message — mirrors
 * DonationTransitionConflictException.
 */
class PaymentTransitionConflictException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
