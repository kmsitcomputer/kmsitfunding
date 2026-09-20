<?php

namespace App\Models\Payment;

use App\Models\Payment\Concerns\GeneratesPaymentUlid;
use Illuminate\Database\Eloquent\Model;

/**
 * IMP-009 — Manual Bank Transfer destination-account catalog
 * (docs/implementation/IMP-009-payment-hub.md "Domain Model").
 * Display/routing configuration only — never a balance, never a Ledger
 * account, holds no secret.
 */
class ManualTransferBankAccount extends Model
{
    use GeneratesPaymentUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
