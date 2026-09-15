<?php

namespace App\Services\Content;

use App\Models\Cms\CmsHomepageAssignment;
use App\Models\Cms\CmsPage;

/**
 * IMP-005 — the category-C homepage read contract (docs/implementation/
 * IMP-005-cms.md section 8/18): singleton designation -> designated page ->
 * current published revision -> published page, or null. Read-only —
 * registers no route and owns no view; "/" stays application-owned
 * (section 8), this resolver only answers "what page, if any, is currently
 * designated AND actually visible".
 *
 * A designation grants no visibility of its own: an unpublished/retired/
 * archived designee resolves to null exactly like an undesignated homepage
 * does. Returns the full CmsPage (with its published revision loaded) rather
 * than a neutral PublishedContent DTO — that shape belongs to the public
 * ContentResolverService slice (section 24), not invented early here.
 */
class HomepageContentResolver
{
    public function resolve(): ?CmsPage
    {
        $assignment = CmsHomepageAssignment::query()->whereKey(1)->first();

        if ($assignment === null || $assignment->page_id === null) {
            return null;
        }

        $page = CmsPage::query()->whereKey($assignment->page_id)->first();

        if ($page === null || $page->status !== 'PUBLISHED' || $page->published_revision_id === null) {
            return null;
        }

        return $page->load('publishedRevision');
    }
}
