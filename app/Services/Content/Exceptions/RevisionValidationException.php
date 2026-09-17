<?php

namespace App\Services\Content\Exceptions;

/**
 * A revision payload/operation violated a structural rule RevisionService
 * enforces before the database would (owner/article_type mismatch, an
 * operation attempted on a revision outside its permitted state, etc.) —
 * docs/implementation/IMP-005-cms.md section 11. Classified so callers can
 * map to a 422 without string-matching the message.
 */
class RevisionValidationException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
