<?php

namespace App\Services\Content;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsContentRevision;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use App\Services\Content\Exceptions\ActiveDraftExistsException;
use App\Services\Content\Exceptions\DraftEditConflictException;
use App\Services\Content\Exceptions\RevisionValidationException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-005 — the ONLY writer of revision PAYLOAD columns (docs/implementation/
 * IMP-005-cms.md section 11 "Write boundary"), through exactly createDraft()/
 * editDraft() while state=DRAFT, plus rollbackByCopy() (which is itself
 * createDraft() under the hood — history is never rewritten, a rollback is a
 * new draft). No generic update()/save()/bulk surface is exposed by this
 * service — that absence IS the boundary.
 *
 * This service deliberately does NOT write cms_pages.latest_draft_revision_id
 * or any other identity pointer: section 18 states PublicationService "is the
 * ONLY writer of... the identity revision pointers" without exception. A
 * caller that needs "the current draft for this owner" queries it directly
 * (CmsPage/CmsArticle::currentDraft()) rather than trusting a pointer this
 * service is not authorized to maintain — see that relation's own doc
 * comment for why this is not a gap, just a different source of truth.
 *
 * Media reference handling (section 19 attachment protocol) is NOT part of
 * this slice — it lands with the media pipeline slice. body_html/
 * og_image_asset_id are accepted and stored as plain payload for now; no
 * cms_media_references bookkeeping happens here yet, and og_image_asset_id
 * is validated only by its DB-level FK (existence, not attachability) until
 * that slice exists. This is a documented, temporary limitation, not a
 * silent one.
 */
class RevisionService
{
    /**
     * Create a new DRAFT revision for $owner. The one and only legal way a
     * revision row is ever created.
     *
     * @param  array{title:string,excerpt?:?string,article_type?:?string,body_html:string,
     *                meta_title?:?string,meta_description?:?string,og_title?:?string,
     *                og_description?:?string,og_image_asset_id?:?int,no_index?:bool}  $payload
     */
    public function createDraft(CmsPage|CmsArticle $owner, array $payload, Principal $author): CmsContentRevision
    {
        return DB::transaction(function () use ($owner, $payload, $author) {
            $lockedOwner = $owner::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();

            if ($lockedOwner->revisions()->where('state', 'DRAFT')->exists()) {
                throw new ActiveDraftExistsException(
                    'Owner already has an active DRAFT revision; edit it or discard it before creating another.'
                );
            }

            $articleType = $this->resolveArticleType($lockedOwner, $payload['article_type'] ?? null);

            $nextRevisionNo = (int) ($lockedOwner->revisions()->max('revision_no') ?? 0) + 1;

            $revision = new CmsContentRevision;
            $revision->forceFill([
                $lockedOwner instanceof CmsPage ? 'page_id' : 'article_id' => $lockedOwner->id,
                'revision_no' => $nextRevisionNo,
                'title' => $payload['title'],
                'excerpt' => $payload['excerpt'] ?? null,
                'article_type' => $articleType,
                'slug_snapshot' => null,
                'body_html' => $payload['body_html'],
                'meta_title' => $payload['meta_title'] ?? null,
                'meta_description' => $payload['meta_description'] ?? null,
                'og_title' => $payload['og_title'] ?? null,
                'og_description' => $payload['og_description'] ?? null,
                'og_image_asset_id' => $payload['og_image_asset_id'] ?? null,
                'no_index' => $payload['no_index'] ?? false,
                'author_principal_id' => $author->id,
                'authored_at' => now(),
                // Explicit rather than relying on the DB column default:
                // the in-memory model would otherwise read back null for
                // these until a fresh() re-fetch, which is a footgun for
                // every caller that uses the object save() just returned.
                'state' => 'DRAFT',
                'edit_version' => 0,
            ]);
            $revision->save();

            return $revision;
        });
    }

    /**
     * Replace the payload of an existing DRAFT revision, under optimistic
     * concurrency control (section 19 step 5 / section 26 flow 3). Never
     * touches a lifecycle column — that whitelist is PublicationService's
     * alone.
     *
     * @param  array<string, mixed>  $payload  same shape as createDraft(), all keys optional
     *                                         (only supplied keys are overwritten)
     */
    public function editDraft(CmsContentRevision $revision, array $payload, int $expectedEditVersion): CmsContentRevision
    {
        return DB::transaction(function () use ($revision, $payload, $expectedEditVersion) {
            $locked = CmsContentRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();

            if ($locked->state !== 'DRAFT') {
                throw new RevisionValidationException(
                    'revision_not_draft',
                    "Revision {$locked->id} is state={$locked->state}; only a DRAFT revision's payload may be edited."
                );
            }

            if ($locked->edit_version !== $expectedEditVersion) {
                throw new DraftEditConflictException(
                    "Expected edit_version {$expectedEditVersion} but revision {$locked->id} is at {$locked->edit_version}."
                );
            }

            $owner = $locked->page_id !== null ? $locked->page : $locked->article;

            if (array_key_exists('article_type', $payload)) {
                $payload['article_type'] = $this->resolveArticleType($owner, $payload['article_type']);
            }

            $payloadColumns = [
                'title', 'excerpt', 'article_type', 'body_html', 'meta_title',
                'meta_description', 'og_title', 'og_description', 'og_image_asset_id', 'no_index',
            ];

            foreach ($payloadColumns as $column) {
                if (array_key_exists($column, $payload)) {
                    $locked->setAttribute($column, $payload[$column]);
                }
            }

            $locked->edit_version = $locked->edit_version + 1;
            $locked->save();

            return $locked;
        });
    }

    /**
     * Create a NEW draft revision copying a historical (PUBLISHED/SUPERSEDED)
     * revision's payload. History is never rewritten — this is a copy, never
     * an edit of the historical row (section 11).
     */
    public function rollbackByCopy(CmsContentRevision $historical, Principal $author): CmsContentRevision
    {
        $owner = $historical->page_id !== null ? $historical->page : $historical->article;

        return $this->createDraft($owner, [
            'title' => $historical->title,
            'excerpt' => $historical->excerpt,
            'article_type' => $historical->article_type,
            'body_html' => $historical->body_html,
            'meta_title' => $historical->meta_title,
            'meta_description' => $historical->meta_description,
            'og_title' => $historical->og_title,
            'og_description' => $historical->og_description,
            'og_image_asset_id' => $historical->og_image_asset_id,
            'no_index' => $historical->no_index,
        ], $author);
    }

    /**
     * Page owner => article_type must be NULL. Article owner => ARTICLE or
     * NEWS, defaulting to ARTICLE when not supplied on creation (section 13
     * CHECK constraint; this is the classified-error mirror of it).
     */
    private function resolveArticleType(CmsPage|CmsArticle $owner, ?string $requested): ?string
    {
        if ($owner instanceof CmsPage) {
            if ($requested !== null) {
                throw new RevisionValidationException(
                    'article_type_on_page',
                    'article_type must not be set for a Page-owned revision.'
                );
            }

            return null;
        }

        $type = $requested ?? 'ARTICLE';

        if (! in_array($type, ['ARTICLE', 'NEWS'], true)) {
            throw new RevisionValidationException(
                'invalid_article_type',
                "article_type must be ARTICLE or NEWS, got '{$type}'."
            );
        }

        return $type;
    }
}
