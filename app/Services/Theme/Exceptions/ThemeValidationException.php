<?php

namespace App\Services\Theme\Exceptions;

/**
 * A theme/template/section/component/navigation/branding operation violated
 * a structural rule the service enforces before the database would
 * (docs/implementation/IMP-006-theme-engine.md). Classified so callers can
 * map to a 422 without string-matching the message.
 */
class ThemeValidationException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
