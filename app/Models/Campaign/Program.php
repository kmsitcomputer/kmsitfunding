<?php

namespace App\Models\Campaign;

use App\Models\Campaign\Concerns\GeneratesUlid;
use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IMP-007 — Program: higher-level organizational/content context
 * (docs/implementation/IMP-007-campaign-program-fund.md section 8).
 * status: DRAFT|PUBLISHED|ARCHIVED.
 */
class Program extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(ProgramMediaAsset::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'created_by_principal_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'updated_by_principal_id');
    }
}
