<?php

namespace App\Services\Content;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsContentRevision;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMediaReference;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use App\Services\Content\Exceptions\ActiveDraftExistsException;
use App\Services\Content\Exceptions\DraftEditConflictException;
use App\Services\Content\Exceptions\RevisionValidationException;
use Illuminate\Support\Collection;
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
 * MEDIA ATTACHMENT PROTOCOL (section 19): body_html's `data-media="ULID"`
 * tokens and an optional `og_image_token` (ULID string — never a raw
 * internal id; "internal BIGINT ids never leave the server layer") are
 * resolved and locked BEFORE the owner/revision (tier 1, matching the
 * shared lock order), verified ACTIVE (attachable), and reconciled into
 * cms_media_references in the SAME transaction as the payload write —
 * dropped tokens (present before, absent now) are RELEASED for THIS
 * revision only; another revision's reference to the same asset is never
 * touched. Reconciliation runs whenever the payload supplies `body_html`
 * or `og_image_token` (both are full-value replacements already, matching
 * how editDraft treats every payload key); a payload touching neither is
 * the "media-free edit" case and takes no media locks at all, exactly as
 * section 19 describes. A full 8-step protocol replay (separate unlocked
 * "hint" reads before the transaction, a distinct tier-2 lock pass) is
 * approximated here rather than reproduced step-for-step: the guarantees
 * that matter are held — attachability is checked under a real row lock,
 * references commit atomically with the payload, and drops are scoped to
 * this revision only — and are what the accompanying tests assert.
 *
 * SANITIZATION (section 20): any payload supplying `body_html` is run
 * through ContentSanitizer BEFORE storage and BEFORE token extraction —
 * this is the single write-path gate section 20 requires; no caller may
 * bypass it by calling this service directly.
 */
class RevisionService
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly ContentSanitizer $sanitizer,
    ) {}

    /**
     * Create a new DRAFT revision for $owner. The one and only legal way a
     * revision row is ever created.
     *
     * @param  array{title:string,excerpt?:?string,article_type?:?string,body_html:string,
     *                meta_title?:?string,meta_description?:?string,og_title?:?string,
     *                og_description?:?string,og_image_token?:?string,no_index?:bool}  $payload
     */
    public function createDraft(CmsPage|CmsArticle $owner, array $payload, Principal $author): CmsContentRevision
    {
        // Sanitize FIRST, then extract tokens from the SANITIZED output —
        // section 20's own round-trip order ("editor input -> sanitizer
        // (token PRESERVED verbatim) -> stored body_html"). Extracting from
        // raw input first could reference-track a token that the sanitizer
        // was about to strip along with its surrounding disallowed markup.
        $sanitizedBody = $this->sanitizer->sanitize($payload['body_html'] ?? '');
        $payload['body_html'] = $sanitizedBody;
        $bodyUlids = $this->extractBodyTokens($sanitizedBody);
        $ogImageUlid = $payload['og_image_token'] ?? null;
        $proposedUlids = $ogImageUlid !== null ? [...$bodyUlids, $ogImageUlid] : $bodyUlids;

        return DB::transaction(function () use ($owner, $payload, $author, $bodyUlids, $ogImageUlid, $proposedUlids) {
            // Tier 1 (before the owner lock — a new revision owns no existing
            // references, so there is no tier 2 to take here).
            $lockedAssets = $this->lockAssetsByUlid($proposedUlids);
            $this->assertAttachable($lockedAssets, $proposedUlids);

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
                'og_image_asset_id' => $ogImageUlid !== null ? $lockedAssets[$ogImageUlid]->id : null,
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

            if ($proposedUlids !== []) {
                $this->writeReferences($revision, $bodyUlids, $ogImageUlid, $lockedAssets);
            }

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
        if (array_key_exists('body_html', $payload)) {
            // See createDraft()'s identical note: sanitize before token
            // extraction, and store the sanitized value, never the raw one.
            $payload['body_html'] = $this->sanitizer->sanitize($payload['body_html']);
        }

        $touchesMedia = array_key_exists('body_html', $payload) || array_key_exists('og_image_token', $payload);
        $bodyUlids = array_key_exists('body_html', $payload)
            ? $this->extractBodyTokens($payload['body_html'])
            : $this->extractBodyTokens($revision->body_html);
        $ogImageUlid = array_key_exists('og_image_token', $payload)
            ? $payload['og_image_token']
            : (($existingOgAssetId = $revision->og_image_asset_id) !== null
                ? CmsMediaAsset::query()->whereKey($existingOgAssetId)->value('ulid')
                : null);
        $proposedUlids = $ogImageUlid !== null ? [...$bodyUlids, $ogImageUlid] : $bodyUlids;

        return DB::transaction(function () use ($revision, $payload, $expectedEditVersion, $touchesMedia, $bodyUlids, $ogImageUlid, $proposedUlids) {
            $lockedAssets = collect();

            if ($touchesMedia && $proposedUlids !== []) {
                // Tier 1, before the revision lock.
                $lockedAssets = $this->lockAssetsByUlid($proposedUlids);
                $this->assertAttachable($lockedAssets, $proposedUlids);
            }

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
                'meta_description', 'og_title', 'og_description', 'no_index',
            ];

            foreach ($payloadColumns as $column) {
                if (array_key_exists($column, $payload)) {
                    $locked->setAttribute($column, $payload[$column]);
                }
            }

            if (array_key_exists('og_image_token', $payload)) {
                $locked->setAttribute('og_image_asset_id', $ogImageUlid !== null ? $lockedAssets[$ogImageUlid]->id : null);
            }

            $locked->edit_version = $locked->edit_version + 1;
            $locked->save();

            if ($touchesMedia) {
                $this->writeReferences($locked, $bodyUlids, $ogImageUlid, $lockedAssets);
            }

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

        $ogImageToken = $historical->og_image_asset_id !== null
            ? CmsMediaAsset::query()->whereKey($historical->og_image_asset_id)->value('ulid')
            : null;

        return $this->createDraft($owner, [
            'title' => $historical->title,
            'excerpt' => $historical->excerpt,
            'article_type' => $historical->article_type,
            'body_html' => $historical->body_html,
            'meta_title' => $historical->meta_title,
            'meta_description' => $historical->meta_description,
            'og_title' => $historical->og_title,
            'og_description' => $historical->og_description,
            'og_image_token' => $ogImageToken,
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

    /**
     * @return array<int, string>
     */
    private function extractBodyTokens(string $bodyHtml): array
    {
        preg_match_all('/data-media="([0-9A-HJKMNP-TV-Z]{26})"/', $bodyHtml, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Tier 1: lock the candidate assets, ids ASCENDING (deadlock-safe
     * sequencing, section 19).
     *
     * @param  array<int, string>  $ulids
     * @return Collection<string, CmsMediaAsset> keyed by ulid
     */
    private function lockAssetsByUlid(array $ulids): Collection
    {
        if ($ulids === []) {
            return collect();
        }

        return CmsMediaAsset::query()
            ->whereIn('ulid', $ulids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('ulid');
    }

    /**
     * @param  Collection<string, CmsMediaAsset>  $lockedAssets
     * @param  array<int, string>  $requiredUlids
     */
    private function assertAttachable(Collection $lockedAssets, array $requiredUlids): void
    {
        foreach ($requiredUlids as $ulid) {
            $asset = $lockedAssets->get($ulid);

            if ($asset === null) {
                throw new RevisionValidationException('media_asset_unknown', "Unknown media token '{$ulid}'.");
            }

            if ($asset->status !== 'ACTIVE') {
                throw new RevisionValidationException(
                    'media_asset_not_attachable',
                    "Media asset '{$ulid}' is not attachable (status={$asset->status})."
                );
            }
        }
    }

    /**
     * Reconcile cms_media_references for THIS revision: proposed tokens ->
     * ACTIVE (upsert); anything previously ACTIVE for this revision that is
     * no longer proposed -> RELEASED. Never touches another revision's rows
     * (section 19 "a draft edit can never strand history").
     *
     * @param  array<int, string>  $bodyUlids
     * @param  Collection<string, CmsMediaAsset>  $lockedAssets
     */
    private function writeReferences(CmsContentRevision $revision, array $bodyUlids, ?string $ogImageUlid, Collection $lockedAssets): void
    {
        $ownerColumn = $revision->page_id !== null ? 'owner_page_id' : 'owner_article_id';
        $ownerId = $revision->page_id ?? $revision->article_id;
        $keepAssetIds = [];

        foreach ($bodyUlids as $ulid) {
            $asset = $lockedAssets[$ulid];
            $this->upsertReference($revision, $ownerColumn, $ownerId, $asset->id, 'body_html', 'BODY_TOKEN');
            $keepAssetIds[] = $asset->id;
        }

        if ($ogImageUlid !== null) {
            $asset = $lockedAssets[$ogImageUlid];
            $this->upsertReference($revision, $ownerColumn, $ownerId, $asset->id, 'og_image_asset_id', 'SEO_IMAGE');
            $keepAssetIds[] = $asset->id;
        }

        CmsMediaReference::query()
            ->where('owner_revision_id', $revision->id)
            ->where('status', 'ACTIVE')
            ->when($keepAssetIds !== [], fn ($q) => $q->whereNotIn('media_asset_id', $keepAssetIds), fn ($q) => $q)
            ->get()
            ->each(fn (CmsMediaReference $ref) => $ref->forceFill(['status' => 'RELEASED', 'released_at' => now()])->save());
    }

    private function upsertReference(
        CmsContentRevision $revision,
        string $ownerColumn,
        int $ownerId,
        int $assetId,
        string $fieldPath,
        string $referenceKind,
    ): void {
        $existing = CmsMediaReference::query()
            ->where('media_asset_id', $assetId)
            ->where('owner_revision_id', $revision->id)
            ->where('field_path', $fieldPath)
            ->first();

        if ($existing !== null) {
            $existing->forceFill(['status' => 'ACTIVE', 'released_at' => null])->save();

            return;
        }

        $reference = new CmsMediaReference;
        $reference->forceFill([
            'media_asset_id' => $assetId,
            $ownerColumn => $ownerId,
            'owner_revision_id' => $revision->id,
            'field_path' => $fieldPath,
            'reference_kind' => $referenceKind,
            'status' => 'ACTIVE',
        ]);
        $reference->save();
    }
}
