<?php

namespace App\Models\Payment;

use App\Models\Donation\Donation;
use App\Models\Payment\Concerns\GeneratesPaymentUlid;
use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IMP-009 — Payment (Attempt) aggregate
 * (docs/implementation/IMP-009-payment-hub.md "Domain Model"). One row =
 * one concrete provider transaction/session against a PENDING Donation.
 * donation_id/provider/amount_minor/currency/idempotency_key are set at
 * creation and NEVER mutated afterward (BR-7); status transitions follow
 * the canonical provider-neutral state machine via
 * PaymentTransitionService, never direct writes from provider callbacks.
 * Never a Ledger/Payment record beyond this domain.
 */
class Payment extends Model
{
    use GeneratesPaymentUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'instructions_payload' => 'array',
            'expires_at' => 'datetime',
            'succeeded_at' => 'datetime',
            'failed_at' => 'datetime',
            'expired_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'verified_by_principal_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'cancelled_by_principal_id');
    }

    public function providerEvents(): HasMany
    {
        return $this->hasMany(PaymentProviderEvent::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ManualTransferEvidence::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['PENDING', 'REQUIRES_ACTION'], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['SUCCEEDED', 'FAILED', 'EXPIRED', 'CANCELLED'], true);
    }
}
