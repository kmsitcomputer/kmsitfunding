<?php

namespace App\Services\Content\Exceptions;

/**
 * Stored-XSS wall violation (docs/implementation/IMP-005-cms.md section 20):
 * a disallowed element (script/style/iframe/object/embed/form/input/base/
 * link/meta/svg/math), an author-supplied `src` on `<img>`, a disallowed
 * `href` scheme, or a malformed/unknown `data-media` token. Reject-only —
 * nothing is stored on violation, matching the path/media-token "reject
 * rather than sanitize" philosophy used throughout this specification.
 */
class ContentSanitizationException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
