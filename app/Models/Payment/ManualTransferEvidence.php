<?php

namespace App\Models\Payment;

use App\Models\Payment\Concerns\GeneratesPaymentUlid;
use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-009 — Manual Bank Transfer proof-of-transfer evidence
 * (docs/implementation/IMP-009-payment-hub.md "Domain Model",
 * HD-IMP009-06/07/08 FINAL / LOCKED). Append-only: resubmission is
 * always a NEW row. file_path/mime_type/size_bytes/declared_* are set
 * at submission and never mutated; reviewed_* are set exactly once at
 * review time. submitted_by_principal_id NULL means a guest submission,
 * mirroring Donation's own guest pattern.
 */
class ManualTransferEvidence extends Model
{
    use GeneratesPaymentUlid;

    protected $table = 'manual_transfer_evidence';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'declared_amount_minor' => 'integer',
            'declared_transferred_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'submitted_by_principal_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Principal::class, 'reviewed_by_principal_id');
    }

    public function isReviewed(): bool
    {
        return $this->review_outcome !== null;
    }
}
