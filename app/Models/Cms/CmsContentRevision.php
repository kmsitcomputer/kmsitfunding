<?php

namespace App\Models\Cms;

use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-005 — versioned content payload + lifecycle (docs/implementation/
 * IMP-005-cms.md section 11 + section 13 "cms_content_revisions"). Exactly
 * one of page_id/article_id is set (DB CHECK XOR, section 13).
 *
 * Write boundary (section 11): PAYLOAD columns (title, excerpt,
 * article_type, slug_snapshot, body_html, meta_*, og_*, no_index,
 * author_principal_id, authored_at) are guarded here because the ONLY
 * legal writers are RevisionService::createDraft()/editDraft() while
 * state=DRAFT — a rule this model cannot express declaratively (it is
 * state-conditional, not column-static) and does not attempt to; the
 * service layer (slice 2) owns that check. LIFECYCLE columns (state,
 * published_at, superseded_at, state_changed_by_principal_id, edit_version)
 * are guarded unconditionally — their only legal writer is
 * PublicationService/RevisionService's dedicated methods, never a generic
 * create/update call. page_id, article_id and revision_no ARE fillable:
 * RevisionService::createDraft() establishes ownership exactly once, at
 * creation — what section 11 forbids is CHANGING them afterwards, which is
 * a service-layer rule (slice 2), not something $guarded can express since
 * it does not distinguish create from update. page_key/article_key/
 * active_draft_*_id are DB-generated (STORED) columns and are never
 * writable from PHP at all.
 */
class CmsContentRevision extends Model
{
    protected $guarded = [
        'id',
        'state',
        'published_at',
        'superseded_at',
        'state_changed_by_principal_id',
        'edit_version',
        'page_key',
        'article_key',
        'active_draft_page_id',
        'active_draft_article_id',
    ];

    protected function casts(): array
    {
        return [
            'no_index' => 'boolean',
            'authored_at' => 'datetime',
            'published_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'page_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(CmsArticle::class, 'article_id');
    }

    public function ogImageAsset(): BelongsTo
    {
        return $this->belongsTo(CmsMediaAsset::class, 'og_image_asset_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'author_principal_id');
    }

    public function stateChangedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'state_changed_by_principal_id');
    }
}
