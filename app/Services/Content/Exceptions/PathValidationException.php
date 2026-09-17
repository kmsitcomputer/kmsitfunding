<?php

namespace App\Services\Content\Exceptions;

/**
 * A path presented to PathService::validate() failed normalization, bounds, or
 * reserved-registry checks (docs/implementation/IMP-005-cms.md section 14).
 * Reject-only: an invalid path is never silently sanitized into a claimable
 * one. `$reason` is a stable machine-readable code (e.g. 'path_traversal',
 * 'path_too_long', 'path_reserved') for callers that need to classify the
 * rejection into an HTTP status without string-matching the message.
 */
class PathValidationException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
