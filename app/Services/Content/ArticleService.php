<?php

namespace App\Services\Content;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsContentRevision;
use App\Models\Rbac\Principal;
use App\Services\Content\Exceptions\RevisionValidationException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-005 — orchestrates Article identity creation + its initial draft.
 * Mirrors PageService exactly (see its doc comment for the delegation and
 * authorization-boundary rationale); the only difference is the owner type
 * and that `article_type` rides through the same revisionPayload
 * RevisionService already validates (ARTICLE|NEWS, defaulting ARTICLE) —
 * Q33/HD-IMP005-05: News is Article classification, never a separate
 * entity or service.
 */
class ArticleService
{
    private const TRACKED_PAYLOAD_FIELDS = [
        'title', 'excerpt', 'body_html', 'meta_title', 'meta_description',
        'og_title', 'og_description', 'og_image_asset_id', 'no_index', 'slug_snapshot', 'article_type',
    ];

    public function __construct(
        private readonly RevisionService $revisionService,
        private readonly ContentAuditLogger $auditLogger,
    ) {}

    public function create(array $revisionPayload, Principal $actor): CmsArticle
    {
        return DB::transaction(function () use ($revisionPayload, $actor) {
            $article = new CmsArticle;
            $article->forceFill([
                'title' => $revisionPayload['title'],
                'status' => 'DRAFT',
                'created_by_principal_id' => $actor->id,
                'updated_by_principal_id' => $actor->id,
            ]);
            $article->save();

            $revision = $this->revisionService->createDraft($article, $revisionPayload, $actor);

            $this->auditLogger->recordArticleCreated(
                $article->id,
                ['revision_id' => $revision->id, 'article_type' => $revision->article_type],
                $actor
            );

            return $article->fresh();
        });
    }

    public function update(CmsArticle $article, array $revisionPayload, int $expectedEditVersion, Principal $actor): CmsContentRevision
    {
        return DB::transaction(function () use ($article, $revisionPayload, $expectedEditVersion, $actor) {
            $draft = $article->currentDraft()->lockForUpdate()->first();

            if ($draft === null) {
                throw new RevisionValidationException(
                    'no_active_draft',
                    "Article {$article->id} has no active DRAFT revision to edit."
                );
            }

            $updated = $this->revisionService->editDraft($draft, $revisionPayload, $expectedEditVersion);

            $article->forceFill(['updated_by_principal_id' => $actor->id])->save();

            // See PageService::update()'s identical note: og_image_token is the
            // payload key, og_image_asset_id is the audited column name.
            $payloadKeys = array_map(fn ($key) => $key === 'og_image_token' ? 'og_image_asset_id' : $key, array_keys($revisionPayload));
            $fieldsChanged = array_values(array_intersect($payloadKeys, self::TRACKED_PAYLOAD_FIELDS));
            $metadata = [
                'revision_id' => $updated->id,
                'fields_changed' => $fieldsChanged,
                'article_type' => $updated->article_type,
            ];

            if ($updated->slug_snapshot !== null) {
                $metadata['slug_snapshot'] = $updated->slug_snapshot;
            }

            $this->auditLogger->recordArticleUpdated($article->id, $metadata, $actor);

            return $updated;
        });
    }
}
