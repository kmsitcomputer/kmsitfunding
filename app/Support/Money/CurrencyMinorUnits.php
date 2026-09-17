<?php

namespace App\Support\Money;

use App\Support\Money\Exceptions\UnknownCurrencyException;

/**
 * IMP-007 (HD-IMP007-02) — centralizes the per-currency minor-unit digit
 * count in ONE place (config/money.php) rather than assuming every currency
 * has exactly two decimal digits. Fail-closed: an unregistered currency
 * throws, never silently defaults.
 */
final class CurrencyMinorUnits
{
    public static function digitsFor(string $currency): int
    {
        $digits = config("money.minor_units.{$currency}");

        if ($digits === null) {
            throw new UnknownCurrencyException($currency);
        }

        return (int) $digits;
    }

    public static function isRegistered(string $currency): bool
    {
        return config("money.minor_units.{$currency}") !== null;
    }
}
