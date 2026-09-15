<?php

namespace App\Services\Theme\Exceptions;

/**
 * An uploaded theme asset failed the section 17 validation pipeline —
 * mirrors App\Services\Content\Exceptions\MediaValidationException exactly.
 */
class ThemeAssetValidationException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
