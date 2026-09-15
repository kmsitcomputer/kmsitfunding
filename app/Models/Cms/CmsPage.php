<?php

namespace App\Models\Cms;

use App\Models\Cms\Concerns\GeneratesUlid;
use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * IMP-005 — managed page identity + lifecycle (docs/implementation/
 * IMP-005-cms.md section 13 "cms_pages"). No path/homepage column: those
 * live in CmsPath / CmsHomepageAssignment (the single-purpose stores).
 *
 * The three revision pointer columns and the lifecycle/schedule columns are
 * deliberately guarded, not fillable: section 11's write boundary means no
 * generic mass-assignable update path may exist for them. The
 * (not-yet-built) PublicationService is the only intended writer of
 * `status`, `*_revision_id`, and the schedule_* columns; PageService is the
 * only intended writer of `title` (mirrored from the published revision).
 * This model layer enforces none of that yet — it is a data-shape only.
 */
class CmsPage extends Model
{
    use GeneratesUlid;

    // 'status' IS fillable: PageService creates the identity row with its
    // initial DRAFT status. What is guarded is the revision-pointer and
    // schedule state, which only PublicationService may ever write (section
    // 11) — status TRANSITIONS after creation are a service-layer rule
    // (slice 2), not something this model layer can express by itself.
    protected $guarded = [
        'id',
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
            'publish_at' => 'datetime',
            'unpublish_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'schedule_version' => 'integer',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(CmsContentRevision::class, 'page_id');
    }

    /**
     * The owner's live draft, read directly rather than through a maintained
     * pointer column: section 18 reserves ALL identity-pointer writes
     * (including latest_draft_revision_id) to PublicationService, so
     * RevisionService::createDraft() never sets that column. The DB's own
     * single-active-draft uniqueness (active_draft_page_id) guarantees at
     * most one row ever matches this query, so it is a safe substitute for
     * the pointer, not a weaker one.
     */
    public function currentDraft(): HasOne
    {
        return $this->hasOne(CmsContentRevision::class, 'page_id')->where('state', 'DRAFT');
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
        return $this->hasMany(CmsPath::class, 'page_id');
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
