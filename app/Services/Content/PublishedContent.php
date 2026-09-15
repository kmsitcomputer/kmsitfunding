<?php

namespace App\Services\Content;

/**
 * IMP-005 — the presentation-neutral published content payload (docs/
 * implementation/IMP-005-cms.md section 24): "stable ULID/kind, title,
 * sanitized body with unresolved media tokens, meta set, canonical path,
 * Article article_type". `bodyHtml` is exactly as stored — still carrying
 * `data-media` placeholder tokens — because resolving those into `src` URLs
 * is the renderer/theme's job (MediaTokenResolver), not this contract's;
 * IMP-005 defines the payload, IMP-006 owns presentation. `ogImageUrl` is
 * the one exception: it is a single meta-tag value, not parsed content, so
 * it is resolved to a real URL here for convenience.
 */
final class PublishedContent
{
    public function __construct(
        public readonly string $ulid,
        public readonly string $kind,
        public readonly string $title,
        public readonly ?string $excerpt,
        public readonly string $bodyHtml,
        public readonly ?string $metaTitle,
        public readonly ?string $metaDescription,
        public readonly ?string $ogTitle,
        public readonly ?string $ogDescription,
        public readonly ?string $ogImageUrl,
        public readonly bool $noIndex,
        public readonly string $canonicalPath,
        public readonly ?string $articleType,
    ) {}
}
