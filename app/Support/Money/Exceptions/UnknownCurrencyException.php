<?php

namespace App\Support\Money\Exceptions;

/**
 * Thrown by CurrencyMinorUnits::digitsFor() for any currency code not present
 * in config/money.php's registry — HD-IMP007-02 explicitly forbids silently
 * assuming 2 minor-unit digits for an unregistered currency.
 */
class UnknownCurrencyException extends \RuntimeException
{
    public function __construct(public readonly string $currency)
    {
        parent::__construct("Currency '{$currency}' is not registered in config/money.php.");
    }
}
