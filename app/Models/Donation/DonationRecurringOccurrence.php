<?php

namespace App\Models\Donation;

use App\Models\Donation\Concerns\GeneratesUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-008 — Recurring Occurrence (docs/implementation/IMP-008-donation.md
 * "Domain Model", HD-IMP008-03). status:
 * SCHEDULED|GENERATED|SKIPPED|FAILED. FAILED is terminal and is never
 * auto-retried (BR-11); the plan's next SCHEDULED occurrence proceeds
 * independently. donation_id is set exactly once, when the occurrence
 * generates its Donation (unique — no double-generation).
 */
class DonationRecurringOccurrence extends Model
{
    protected $table = 'donation_recurring_occurrences';

    use GeneratesUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DonationRecurringPlan::class, 'recurring_plan_id');
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }
}
