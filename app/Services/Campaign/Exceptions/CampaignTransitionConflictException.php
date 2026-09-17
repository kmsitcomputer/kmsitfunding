<?php

namespace App\Services\Campaign\Exceptions;

/**
 * A Campaign lifecycle transition (submit/approve/reject/publish/close) was
 * attempted against a Campaign no longer (or not yet) in the required
 * status, or the fund_id was missing/inactive at publish time — BR-7/AC-007-
 * 005/007/014 (docs/implementation/IMP-007-campaign-program-fund.md).
 */
class CampaignTransitionConflictException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
