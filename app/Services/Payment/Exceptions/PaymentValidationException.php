<?php

namespace App\Services\Payment\Exceptions;

/**
 * A Payment write violated a structural rule the service enforces before
 * the database would (docs/implementation/IMP-009-payment-hub.md
 * "Business Rules" / "Failure Semantics"). Classified so callers can map
 * to a 422 without string-matching the message — mirrors
 * DonationValidationException.
 */
class PaymentValidationException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
