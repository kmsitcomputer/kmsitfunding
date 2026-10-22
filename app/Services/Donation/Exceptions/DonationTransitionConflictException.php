<?php

namespace App\Services\Donation\Exceptions;

/**
 * A Donation lifecycle transition or idempotency replay was attempted
 * against a Donation no longer (or not yet) in the required state, or an
 * idempotency key was reused with a materially different payload
 * (docs/implementation/IMP-008-donation.md BR-7 / "Idempotency" / "Error
 * Handling"). Classified so callers can map to a 409 without
 * string-matching the message — mirrors
 * CampaignTransitionConflictException.
 */
class DonationTransitionConflictException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
