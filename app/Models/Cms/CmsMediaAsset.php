<?php

namespace App\Models\Cms;

use App\Models\Cms\Concerns\GeneratesUlid;
use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IMP-005 — content media library (docs/implementation/IMP-005-cms.md
 * section 13 "cms_media_assets"). status ACTIVE|ARCHIVED|PURGED (section
 * 27) — the archive/purge transitions and their attribution columns are
 * guarded here; MediaService (slice 2) owns those writes. `stored_filename`
 * is GENERATED (server-chosen, never user input, section 19) and guarded.
 */
class CmsMediaAsset extends Model
{
    use GeneratesUlid;

    protected $guarded = [
        'id',
        'stored_filename',
        'status',
        'archived_at',
        'archived_by_principal_id',
        'purged_at',
        'purge_attempts',
        'last_purge_error',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'archived_at' => 'datetime',
            'purged_at' => 'datetime',
            'purge_attempts' => 'integer',
        ];
    }

    public function references(): HasMany
    {
        return $this->hasMany(CmsMediaReference::class, 'media_asset_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'uploaded_by_principal_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'archived_by_principal_id');
    }

    /**
     * Route-model binding by public ULID, never the internal BIGINT id —
     * see CmsPage::getRouteKeyName()'s identical note.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
