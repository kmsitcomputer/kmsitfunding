<?php

namespace App\Models\Campaign;

use App\Models\Campaign\Concerns\GeneratesUlid;
use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IMP-007 — Campaign: a specific fundraising initiative
 * (docs/implementation/IMP-007-campaign-program-fund.md sections 8/8a/8b).
 * status: DRAFT|REVIEW|APPROVED|PUBLISHED|CLOSED (HD-IMP007-01).
 * target_amount_minor is an integer minor-unit amount, never decimal/float
 * (HD-IMP007-02) — manipulate exclusively via App\Support\Money\Money.
 */
class Campaign extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'target_amount_minor' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(CampaignMediaAsset::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'created_by_principal_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'updated_by_principal_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'submitted_by_principal_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'approved_by_principal_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'published_by_principal_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'closed_by_principal_id');
    }
}
