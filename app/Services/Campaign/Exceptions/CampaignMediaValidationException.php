<?php

namespace App\Services\Campaign\Exceptions;

/**
 * A Program/Campaign media upload failed adversarial validation (MIME
 * sniff, extension allow-list, dimension-bomb guard) — mirrors
 * ThemeAssetValidationException exactly.
 */
class CampaignMediaValidationException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
