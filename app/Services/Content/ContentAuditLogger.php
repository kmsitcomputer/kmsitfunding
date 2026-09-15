<?php

namespace App\Services\Content;

use App\Models\Rbac\Principal;
use App\Services\Audit\AuditEventInput;
use App\Services\Audit\AuditWriter;

/**
 * IMP-005 — thin sink wrapper over the canonical AuditWriter (mirrors
 * RbacAuditLogger's role, docs/implementation/IMP-005-cms.md section 12).
 * Metadata ASSEMBLY (which conditional keys apply, the omit-never-null rule)
 * is the calling service's job, close to the data that decides it — this
 * class only forwards an already-correct payload to the writer, inside the
 * SAME transaction the caller is already in (append-inside, per Q26/IMP-004
 * NON_CRITICAL semantics: failure here is reported, never propagated, and
 * never rolls back the business mutation — that guarantee lives in
 * AuditWriter itself, not here).
 *
 * One method per content.* event this slice wires (13 of the 20 registered
 * in ContentAuditEventRegistrar — the remaining 7 need pieces not yet built:
 * scheduler, media.updated's metadata-edit call site, media.purged's
 * cleanup job, path.released's standalone release endpoint).
 */
class ContentAuditLogger
{
    public function __construct(private readonly AuditWriter $writer) {}

    public function recordPageCreated(int $pageId, array $metadata, Principal $actor): void
    {
        $this->emit('content.page.created', 'cms_page', $pageId, $metadata, $actor);
    }

    public function recordPageUpdated(int $pageId, array $metadata, Principal $actor): void
    {
        $this->emit('content.page.updated', 'cms_page', $pageId, $metadata, $actor);
    }

    public function recordPagePublished(int $pageId, array $metadata, Principal $actor): void
    {
        $this->emit('content.page.published', 'cms_page', $pageId, $metadata, $actor);
    }

    public function recordPageUnpublished(int $pageId, array $metadata, Principal $actor): void
    {
        $this->emit('content.page.unpublished', 'cms_page', $pageId, $metadata, $actor);
    }

    public function recordPageArchived(int $pageId, array $metadata, Principal $actor): void
    {
        $this->emit('content.page.archived', 'cms_page', $pageId, $metadata, $actor);
    }

    public function recordArticleCreated(int $articleId, array $metadata, Principal $actor): void
    {
        $this->emit('content.article.created', 'cms_article', $articleId, $metadata, $actor);
    }

    public function recordArticleUpdated(int $articleId, array $metadata, Principal $actor): void
    {
        $this->emit('content.article.updated', 'cms_article', $articleId, $metadata, $actor);
    }

    public function recordArticlePublished(int $articleId, array $metadata, Principal $actor): void
    {
        $this->emit('content.article.published', 'cms_article', $articleId, $metadata, $actor);
    }

    public function recordArticleUnpublished(int $articleId, array $metadata, Principal $actor): void
    {
        $this->emit('content.article.unpublished', 'cms_article', $articleId, $metadata, $actor);
    }

    public function recordArticleArchived(int $articleId, array $metadata, Principal $actor): void
    {
        $this->emit('content.article.archived', 'cms_article', $articleId, $metadata, $actor);
    }

    public function recordMediaUploaded(int $assetId, array $metadata, Principal $actor): void
    {
        $this->emit('content.media.uploaded', 'cms_media_asset', $assetId, $metadata, $actor);
    }

    public function recordMediaArchived(int $assetId, array $metadata, Principal $actor): void
    {
        $this->emit('content.media.archived', 'cms_media_asset', $assetId, $metadata, $actor);
    }

    public function recordHomepageAssigned(array $metadata, Principal $actor): void
    {
        // The singleton row's fixed id (1) is the subject — section 12's
        // registry-entry shape names it explicitly.
        $this->emit('content.homepage.assigned', 'cms_homepage_assignment', 1, $metadata, $actor);
    }

    private function emit(string $eventType, string $subjectType, int $subjectId, array $metadata, Principal $actor): void
    {
        $this->writer->record(new AuditEventInput(
            eventType: $eventType,
            actor: $actor,
            subjectType: $subjectType,
            subjectId: $subjectId,
            metadata: $metadata,
        ));
    }
}
