<?php

namespace App\Services\Content\Exceptions;

/**
 * A publish()/unpublish() request violated a structural or lifecycle rule
 * PublicationService enforces before touching the database (candidate/owner
 * mismatch, illegal state transition, missing target path on first
 * publication, etc.) — docs/implementation/IMP-005-cms.md sections 10/11/14.
 */
class PublicationValidationException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
