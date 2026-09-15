<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-005 — the canonical media reference model (docs/implementation/
 * IMP-005-cms.md section 13 "cms_media_references", IMP005-SPEC-05). The
 * one authoritative answer to "is this asset used?" — rows are written only
 * inside the service-owned transaction that writes the referencing
 * revision (section 19 attachment protocol). Guarded entirely: no
 * legitimate direct-fill path exists yet at this layer (slice 2 builds it).
 */
class CmsMediaReference extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
        ];
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(CmsMediaAsset::class, 'media_asset_id');
    }

    public function ownerPage(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'owner_page_id');
    }

    public function ownerArticle(): BelongsTo
    {
        return $this->belongsTo(CmsArticle::class, 'owner_article_id');
    }

    public function ownerRevision(): BelongsTo
    {
        return $this->belongsTo(CmsContentRevision::class, 'owner_revision_id');
    }
}
