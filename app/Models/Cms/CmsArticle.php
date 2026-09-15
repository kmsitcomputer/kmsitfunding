<?php

namespace App\Models\Cms;

use App\Models\Cms\Concerns\GeneratesUlid;
use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IMP-005 — managed article identity + lifecycle (docs/implementation/
 * IMP-005-cms.md section 13 "cms_articles"). `excerpt` and
 * `first_published_at` are identity-level CURRENT PROJECTIONS maintained
 * only by the publish transaction (section 11 "Identity projections") —
 * guarded here for the same write-boundary reason as CmsPage's pointer
 * columns. `article_type` lives on the revision, not here.
 */
class CmsArticle extends Model
{
    use GeneratesUlid;

    // 'status' IS fillable (see CmsPage's identical note) — ArticleService
    // creates the identity row with its initial DRAFT status.
    protected $guarded = [
        'id',
        'excerpt',
        'first_published_at',
        'latest_draft_revision_id',
        'published_revision_id',
        'scheduled_revision_id',
        'schedule_version',
        'scheduled_by_principal_id',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'first_published_at' => 'datetime',
            'publish_at' => 'datetime',
            'unpublish_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'schedule_version' => 'integer',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(CmsContentRevision::class, 'article_id');
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(CmsContentRevision::class, 'published_revision_id');
    }

    public function latestDraftRevision(): BelongsTo
    {
        return $this->belongsTo(CmsContentRevision::class, 'latest_draft_revision_id');
    }

    public function scheduledRevision(): BelongsTo
    {
        return $this->belongsTo(CmsContentRevision::class, 'scheduled_revision_id');
    }

    public function paths(): HasMany
    {
        return $this->hasMany(CmsPath::class, 'article_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'created_by_principal_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'updated_by_principal_id');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'scheduled_by_principal_id');
    }
}
