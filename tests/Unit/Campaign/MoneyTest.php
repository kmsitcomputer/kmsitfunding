<?php

namespace Tests\Unit\Campaign;

use App\Support\Money\Exceptions\UnknownCurrencyException;
use App\Support\Money\Money;
use Tests\TestCase;

/**
 * IMP-007 (HD-IMP007-02) — the canonical Money value object.
 * docs/implementation/IMP-007-campaign-program-fund.md section 8a.
 */
class MoneyTest extends TestCase
{
    public function test_construction_exposes_amount_minor_and_currency(): void
    {
        $money = Money::ofMinorUnits(1000000, 'IDR');

        $this->assertSame(1000000, $money->amountMinor());
        $this->assertSame('IDR', $money->currency());
        $this->assertIsInt($money->amountMinor());
    }

    public function test_equality_compares_amount_and_currency(): void
    {
        $a = Money::ofMinorUnits(500, 'USD');
        $b = Money::ofMinorUnits(500, 'USD');
        $c = Money::ofMinorUnits(500, 'IDR');
        $d = Money::ofMinorUnits(600, 'USD');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
        $this->assertFalse($a->equals($d));
    }

    public function test_format_uses_registered_two_digit_minor_unit_for_idr(): void
    {
        $money = Money::ofMinorUnits(1000000, 'IDR');

        $this->assertSame('IDR 10.000,00', $money->format());
    }

    public function test_format_uses_registered_zero_digit_minor_unit_for_jpy(): void
    {
        // JPY is registered with 0 minor-unit digits (config/money.php) —
        // proving NOT every currency is assumed to have 2 digits.
        $money = Money::ofMinorUnits(500, 'JPY');

        $this->assertSame('JPY 500', $money->format());
    }

    public function test_format_uses_registered_three_digit_minor_unit_for_kwd(): void
    {
        $money = Money::ofMinorUnits(1500, 'KWD');

        $this->assertSame('KWD 1,500', $money->format());
    }

    public function test_construction_with_unregistered_currency_throws(): void
    {
        $this->expectException(UnknownCurrencyException::class);

        Money::ofMinorUnits(100, 'XXX');
    }

    public function test_format_never_exposes_a_float_as_the_authoritative_amount(): void
    {
        $money = Money::ofMinorUnits(1234, 'USD');

        // The authoritative value is always the integer minor-unit amount —
        // format() is display-only and its return type is a string, never
        // a float leaking out as if it were authoritative.
        $this->assertIsInt($money->amountMinor());
        $this->assertIsString($money->format());
    }
}
