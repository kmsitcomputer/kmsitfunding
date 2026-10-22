<?php

namespace App\Models\Donation;

use App\Models\Campaign\Campaign;
use App\Models\Donation\Concerns\GeneratesUlid;
use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-008 — Donation: donor intent toward one eligible Campaign
 * (docs/implementation/IMP-008-donation.md "Domain Model", HD-IMP008-01A).
 * status: PENDING|SUCCEEDED|FAILED|CANCELLED|EXPIRED. amount_minor/currency
 * are manipulated exclusively via App\Support\Money\Money. donor_principal_id
 * NULL means guest (BR-2 XOR guard below). Never a Ledger/Payment record.
 */
class Donation extends Model
{
    use GeneratesUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'is_anonymous' => 'boolean',
            'succeeded_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Application-level guard for the BR-2 CHECK invariant — the
        // second, mandatory enforcement layer per "Database Impact"'s
        // defense-in-depth pattern, and the ONLY enforcement layer where
        // the driver cannot carry the CHECK (SQLite), mirroring
        // Principal::isConsistent's own saving guard.
        static::saving(function (self $donation) {
            if (! self::isDonorPathConsistent($donation)) {
                throw new \RuntimeException(
                    'Donation donor path violates the BR-2 invariant: exactly one of donor_principal_id (authenticated) or guest_name+guest_email (guest) must be populated.'
                );
            }
        });
    }

    public static function isDonorPathConsistent(self $donation): bool
    {
        $authenticated = $donation->donor_principal_id !== null;

        if (! $authenticated) {
            return $donation->guest_name !== null && $donation->guest_email !== null;
        }

        return $donation->guest_name === null && $donation->guest_email === null;
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'donor_principal_id');
    }

    public function recurringOccurrence(): BelongsTo
    {
        return $this->belongsTo(DonationRecurringOccurrence::class, 'recurring_occurrence_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'cancelled_by_principal_id');
    }
}
