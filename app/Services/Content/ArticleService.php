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
    public function __construct(private readonly RevisionService $revisionService) {}

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

            $this->revisionService->createDraft($article, $revisionPayload, $actor);

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

            return $updated;
        });
    }
}
