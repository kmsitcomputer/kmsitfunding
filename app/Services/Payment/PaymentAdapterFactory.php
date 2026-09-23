<?php

namespace App\Services\Payment;

use App\Adapters\Payment\ManualTransferAdapter;
use App\Adapters\Payment\StripeAdapter;
use App\Adapters\Payment\TripayAdapter;
use App\Adapters\Payment\XenditAdapter;
use App\Contracts\Payment\PaymentProviderAdapter;
use App\Services\Payment\Exceptions\PaymentValidationException;

/**
 * IMP-009 — resolves the provider-neutral adapter for a payments.provider
 * value (docs/implementation/IMP-009-payment-hub.md "Provider Adapter
 * Architecture"). The closed registry mirrors IMP-008 BR-9's
 * recurring-frequency allow-list pattern: a future authorized adapter
 * is added here without redesigning the provider column.
 */
class PaymentAdapterFactory
{
    /**
     * @return array<string>
     */
    public function providers(): array
    {
        return ['manual_transfer', 'tripay', 'xendit', 'stripe'];
    }

    public function for(string $provider): PaymentProviderAdapter
    {
        return match ($provider) {
            'manual_transfer' => app(ManualTransferAdapter::class),
            'tripay' => app(TripayAdapter::class),
            'xendit' => app(XenditAdapter::class),
            'stripe' => app(StripeAdapter::class),
            default => throw new PaymentValidationException(
                'invalid_provider',
                "Provider '{$provider}' is not an approved payment provider."
            ),
        };
    }
}
