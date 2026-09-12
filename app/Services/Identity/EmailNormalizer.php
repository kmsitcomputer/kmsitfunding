<?php

namespace App\Services\Identity;

/**
 * One deterministic email normalization path, used consistently for
 * registration, login, invitation matching, password reset, email change,
 * uniqueness lookup, and bootstrap (see "Email Normalization" in
 * docs/implementation/IMP-002-identity-authentication.md).
 *
 * Explicitly provider-neutral: no Gmail-style dot-removal, no plus-alias
 * stripping, no other provider-specific mailbox rewriting.
 */
class EmailNormalizer
{
    public function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
