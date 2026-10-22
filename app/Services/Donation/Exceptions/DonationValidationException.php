<?php

namespace App\Services\Donation\Exceptions;

/**
 * A Donation write violated a structural rule the service enforces before
 * the database would (docs/implementation/IMP-008-donation.md "Business
 * Rules" / "Error Handling"). Classified so callers can map to a 422
 * without string-matching the message — mirrors CampaignValidationException.
 */
class DonationValidationException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
