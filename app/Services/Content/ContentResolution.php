<?php

namespace App\Services\Content;

/**
 * The result of ContentResolverService::resolve() — exactly one of FOUND
 * (render $content), REDIRECT (301 to $redirectTo), or NOT_FOUND (404).
 * A discriminated small value object rather than a bare nullable, so a
 * caller cannot mistake "no redirect target" for "not found".
 */
final class ContentResolution
{
    private function __construct(
        public readonly string $type,
        public readonly ?PublishedContent $content = null,
        public readonly ?string $redirectTo = null,
    ) {}

    public static function found(PublishedContent $content): self
    {
        return new self('found', content: $content);
    }

    public static function redirect(string $to): self
    {
        return new self('redirect', redirectTo: $to);
    }

    public static function notFound(): self
    {
        return new self('not_found');
    }
}
