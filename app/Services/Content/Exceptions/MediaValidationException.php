<?php

namespace App\Services\Content\Exceptions;

/**
 * An uploaded file failed the section 19 validation pipeline (extension/
 * MIME/size/dimension/decode check), or a media operation was attempted
 * against a row in a state that forbids it (e.g. attaching an ARCHIVED
 * asset). Reject-only — nothing is stored/mutated on violation.
 */
class MediaValidationException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
