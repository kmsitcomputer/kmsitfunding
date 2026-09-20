<?php

namespace Tests\Unit\Payment;

use App\Services\Payment\Exceptions\PaymentValidationException;
use App\Support\Money\Exceptions\UnknownCurrencyException;
use App\Support\Money\ProviderAmountConverter;
use Tests\TestCase;

/**
 * IMP-009 HD-IMP009-13 — canonical Money / provider unit conversion
 * (F-08, unit layer). Canonical internal representation is
 * amount_minor + CurrencyMinorUnits (IDR digits = 2, so Rp 10,000 =
 * 1,000,000 canonical amount_minor). Integer-only arithmetic: exact
 * divisibility is required, never silent rounding, never floats.
 */
class ProviderAmountConverterTest extends TestCase
{
    public function test_tripay_converts_canonical_idr_minor_to_whole_idr(): void
    {
        $this->assertSame(10000, ProviderAmountConverter::toProviderUnits(1000000, 'IDR', 'tripay'));
        $this->assertSame(1000000, ProviderAmountConverter::toCanonicalMinor(10000, 'IDR', 'tripay'));
    }

    public function test_tripay_rejects_non_idr_currency(): void
    {
        try {
            ProviderAmountConverter::toProviderUnits(10000, 'USD', 'tripay');
            $this->fail('Tripay must reject non-IDR currency.');
        } catch (PaymentValidationException $e) {
            $this->assertSame('provider_currency_unsupported', $e->reason);
        }
    }

    public function test_tripay_rejects_non_representable_amount_without_rounding(): void
    {
        try {
            ProviderAmountConverter::toProviderUnits(1000001, 'IDR', 'tripay');
            $this->fail('A non-whole-rupiah canonical value must be rejected, never rounded.');
        } catch (PaymentValidationException $e) {
            $this->assertSame('provider_amount_not_representable', $e->reason);
        }
    }

    public function test_xendit_converts_canonical_minor_to_major_units(): void
    {
        $this->assertSame(10000, ProviderAmountConverter::toProviderUnits(1000000, 'IDR', 'xendit'));
        $this->assertSame(1000000, ProviderAmountConverter::toCanonicalMinor(10000, 'IDR', 'xendit'));
        $this->assertSame(10000, ProviderAmountConverter::toProviderUnits(1000000, 'USD', 'xendit'));
        $this->assertSame(10000, ProviderAmountConverter::toProviderUnits(10000, 'JPY', 'xendit'));
        $this->assertSame(10000, ProviderAmountConverter::toProviderUnits(10000000, 'KWD', 'xendit'));
    }

    public function test_xendit_rejects_non_representable_amount_without_rounding(): void
    {
        try {
            ProviderAmountConverter::toProviderUnits(1000001, 'IDR', 'xendit');
            $this->fail('A fractional-major canonical value must be rejected, never rounded.');
        } catch (PaymentValidationException $e) {
            $this->assertSame('provider_amount_not_representable', $e->reason);
        }
    }

    public function test_stripe_converts_to_smallest_currency_unit(): void
    {
        $this->assertSame(1000000, ProviderAmountConverter::toProviderUnits(1000000, 'IDR', 'stripe'));
        $this->assertSame(1000000, ProviderAmountConverter::toCanonicalMinor(1000000, 'IDR', 'stripe'));
        $this->assertSame(1000000, ProviderAmountConverter::toProviderUnits(1000000, 'USD', 'stripe'));
        $this->assertSame(10000, ProviderAmountConverter::toProviderUnits(10000, 'JPY', 'stripe'));
        $this->assertSame(10000000, ProviderAmountConverter::toProviderUnits(10000000, 'KWD', 'stripe'));
    }

    public function test_manual_transfer_is_the_identity_conversion(): void
    {
        $this->assertSame(1000000, ProviderAmountConverter::toProviderUnits(1000000, 'IDR', 'manual_transfer'));
        $this->assertSame(1000000, ProviderAmountConverter::toCanonicalMinor(1000000, 'IDR', 'manual_transfer'));
    }

    public function test_stripe_decimal_table(): void
    {
        $this->assertSame(0, ProviderAmountConverter::stripeDecimalsFor('JPY'));
        $this->assertSame(3, ProviderAmountConverter::stripeDecimalsFor('KWD'));
        $this->assertSame(2, ProviderAmountConverter::stripeDecimalsFor('IDR'));
        $this->assertSame(2, ProviderAmountConverter::stripeDecimalsFor('USD'));
    }

    public function test_unregistered_currency_is_rejected_never_assumed(): void
    {
        $this->expectException(UnknownCurrencyException::class);

        ProviderAmountConverter::toProviderUnits(10000, 'XXX', 'stripe');
    }

    public function test_unknown_provider_is_rejected(): void
    {
        try {
            ProviderAmountConverter::toProviderUnits(10000, 'IDR', 'midtrans');
            $this->fail('An unapproved provider must be rejected.');
        } catch (PaymentValidationException $e) {
            $this->assertSame('invalid_provider', $e->reason);
        }
    }

    public function test_non_positive_amounts_are_rejected(): void
    {
        try {
            ProviderAmountConverter::toProviderUnits(0, 'IDR', 'stripe');
            $this->fail('A zero amount must be rejected.');
        } catch (PaymentValidationException $e) {
            $this->assertSame('invalid_amount', $e->reason);
        }
    }
}
