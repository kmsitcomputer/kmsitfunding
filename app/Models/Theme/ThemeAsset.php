<?php

namespace App\Models\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\Concerns\GeneratesUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-006 — a theme-owned uploaded file (docs/implementation/
 * IMP-006-theme-engine.md section 17). `stored_filename` is server-generated
 * (ULID), guarded here exactly as CmsMediaAsset guards it.
 */
class ThemeAsset extends Model
{
    use GeneratesUlid;

    protected $guarded = [
        'id',
        'stored_filename',
        'status',
        'archived_at',
        'archived_by_principal_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'uploaded_by_principal_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'archived_by_principal_id');
    }
}
