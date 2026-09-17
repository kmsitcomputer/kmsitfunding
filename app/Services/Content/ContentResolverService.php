<?php

namespace App\Services\Content;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPath;
use App\Services\Content\Exceptions\PathValidationException;

/**
 * IMP-005 — the public read path (docs/implementation/IMP-005-cms.md
 * section 14 "RESOLUTION AND 404 BEHAVIOR"): normalized path -> ACTIVE
 * cms_paths claim -> CURRENT content (published payload) or REDIRECT (301
 * to the owner's ACTIVE CURRENT claim) -> presentation-neutral payload, or
 * NOT_FOUND. Read-only; never mutates; 404s without echoing hidden state
 * (never a status echo for DRAFT/RETIRED/ARCHIVED content — section 28).
 *
 * RESERVATION and RESOLUTION are different questions (section 14): a path
 * can be reserved (an ACTIVE row exists) without resolving (owner not
 * PUBLISHED) — this service answers resolution only; PathService answers
 * reservation.
 */
class ContentResolverService
{
    public function __construct(
        private readonly PathService $pathService,
        private readonly MediaTokenResolver $mediaTokenResolver,
    ) {}

    public function resolve(string $rawPath): ContentResolution
    {
        try {
            $normalized = $this->pathService->normalize($rawPath);
        } catch (PathValidationException) {
            // A malformed incoming path simply does not resolve — never a
            // 500, never a disclosure of why (section 28).
            return ContentResolution::notFound();
        }

        $claim = CmsPath::query()->where('path', $normalized)->where('status', 'ACTIVE')->first();

        if ($claim === null) {
            return ContentResolution::notFound();
        }

        $owner = $claim->page_id !== null
            ? CmsPage::query()->whereKey($claim->page_id)->first()
            : CmsArticle::query()->whereKey($claim->article_id)->first();

        if ($owner === null || $owner->status !== 'PUBLISHED') {
            return ContentResolution::notFound();
        }

        if ($claim->purpose === 'CURRENT') {
            return ContentResolution::found($this->toPublishedContent($owner));
        }

        // REDIRECT: 301 to the owner's current ACTIVE CURRENT claim, if it
        // still has one (it always does once PUBLISHED — a claim is only
        // ever released by an explicit governed act, never automatically).
        $ownerColumn = $owner instanceof CmsPage ? 'page_id' : 'article_id';
        $currentClaim = CmsPath::query()
            ->where($ownerColumn, $owner->id)
            ->where('purpose', 'CURRENT')
            ->where('status', 'ACTIVE')
            ->first();

        return $currentClaim !== null
            ? ContentResolution::redirect($currentClaim->path)
            : ContentResolution::notFound();
    }

    private function toPublishedContent(CmsPage|CmsArticle $owner): PublishedContent
    {
        $revision = $owner->publishedRevision;

        return new PublishedContent(
            ulid: $owner->ulid,
            kind: $owner instanceof CmsPage ? 'page' : 'article',
            title: $revision->title,
            excerpt: $revision->excerpt,
            bodyHtml: $revision->body_html,
            metaTitle: $revision->meta_title,
            metaDescription: $revision->meta_description,
            ogTitle: $revision->og_title,
            ogDescription: $revision->og_description,
            ogImageUrl: $revision->og_image_asset_id !== null
                ? $this->mediaTokenResolver->resolveUrl($revision->ogImageAsset->ulid)
                : null,
            noIndex: (bool) $revision->no_index,
            canonicalPath: $revision->slug_snapshot,
            articleType: $revision->article_type,
        );
    }
}
