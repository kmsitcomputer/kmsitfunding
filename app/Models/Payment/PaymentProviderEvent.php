<?php

namespace App\Models\Payment;

use App\Models\Payment\Concerns\GeneratesPaymentUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-009 — Provider Event forensic record
 * (docs/implementation/IMP-009-payment-hub.md "Domain Model"). The raw
 * record of every inbound webhook/callback and its verification/
 * processing outcome — security/audit evidence, never business state.
 * raw_payload_ciphertext is never read by business-logic code paths as
 * an authoritative source.
 */
class PaymentProviderEvent extends Model
{
    use GeneratesPaymentUlid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'received_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
