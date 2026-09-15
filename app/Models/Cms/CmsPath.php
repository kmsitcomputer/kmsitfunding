<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-005 — the single canonical public CMS path namespace (docs/implementation/
 * IMP-005-cms.md section 13 "cms_paths", IMP005-SPEC-03). `active_path` and
 * `active_current_owner` are DB-generated (STORED) columns and are never
 * writable from PHP. Rows are written only inside PathService's claim/
 * rename/release operations (section 14) — guarded entirely here since no
 * legitimate direct-fill path exists yet at this layer (slice 2 builds it).
 */
class CmsPath extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
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

    public function revision(): BelongsTo
    {
        return $this->belongsTo(CmsContentRevision::class, 'revision_id');
    }
}
