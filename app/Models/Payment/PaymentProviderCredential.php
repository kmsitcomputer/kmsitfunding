<?php

namespace App\Models\Payment;

use Illuminate\Database\Eloquent\Model;

/**
 * IMP-009 — provider secret configuration
 * (docs/implementation/IMP-009-payment-hub.md "Domain Model" /
 * "Configuration"). encrypted_secret is application-layer encrypted
 * (Laravel's encrypter, APP_KEY-backed) — no plaintext secret column,
 * ever. Never serialized through any read API/admin response; see
 * $hidden below (masked/omitted unconditionally per spec).
 */
class PaymentProviderCredential extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['encrypted_secret'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }
}
