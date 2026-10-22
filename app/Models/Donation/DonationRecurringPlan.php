<?php

namespace App\Models\Donation;

use App\Models\Campaign\Campaign;
use App\Models\Donation\Concerns\GeneratesUlid;
use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IMP-008 — Recurring Plan (docs/implementation/IMP-008-donation.md
 * "Domain Model", HD-IMP008-01B/HD-IMP008-02/HD-IMP008-03).
 * Authenticated-donor-only. status: ACTIVE|PAUSED|CANCELLED|COMPLETED.
 * Owning donor may pause/resume/cancel; ORGANIZATION-scoped override
 * performs the same transitions (same service methods, distinct policy
 * path).
 */
class DonationRecurringPlan extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'is_anonymous' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'next_occurrence_at' => 'datetime',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'donor_principal_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function pausedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'paused_by_principal_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'cancelled_by_principal_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(DonationRecurringOccurrence::class, 'recurring_plan_id');
    }
}
