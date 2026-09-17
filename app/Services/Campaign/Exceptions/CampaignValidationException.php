<?php

namespace App\Services\Campaign\Exceptions;

/**
 * A Program/Campaign/Fund write violated a structural rule the service
 * enforces before the database would (docs/implementation/
 * IMP-007-campaign-program-fund.md). Classified so callers can map to a
 * 422 without string-matching the message.
 */
class CampaignValidationException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
