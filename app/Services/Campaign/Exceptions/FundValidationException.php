<?php

namespace App\Services\Campaign\Exceptions;

/**
 * A Fund write violated a structural rule (docs/implementation/
 * IMP-007-campaign-program-fund.md BR-5).
 */
class FundValidationException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
