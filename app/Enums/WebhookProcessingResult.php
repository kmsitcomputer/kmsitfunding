<?php

namespace App\Enums;

/**
 * IMP-009 — inbound provider-event processing outcomes
 * (docs/implementation/IMP-009-payment-hub.md "Domain Model"
 * payment_provider_events). DUPLICATE is not a rejection — it is the
 * expected outcome of at-least-once webhook delivery.
 */
enum WebhookProcessingResult: string
{
    case Accepted = 'ACCEPTED';
    case RejectedInvalidSignature = 'REJECTED_INVALID_SIGNATURE';
    case RejectedUnknownReference = 'REJECTED_UNKNOWN_REFERENCE';
    case RejectedAmountMismatch = 'REJECTED_AMOUNT_MISMATCH';
    case RejectedCurrencyMismatch = 'REJECTED_CURRENCY_MISMATCH';
    case RejectedMalformed = 'REJECTED_MALFORMED';
    case Duplicate = 'DUPLICATE';

    public function isRejection(): bool
    {
        return $this !== self::Accepted && $this !== self::Duplicate;
    }
}
