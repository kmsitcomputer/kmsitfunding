<?php

namespace App\Models\Campaign;

use App\Models\Campaign\Concerns\GeneratesUlid;
use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-007 — a Program-owned uploaded file, mirroring ThemeAsset's shape
 * exactly. `stored_filename` is server-generated (ULID), guarded here
 * exactly as ThemeAsset/CmsMediaAsset guard it.
 */
class ProgramMediaAsset extends Model
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

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
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
