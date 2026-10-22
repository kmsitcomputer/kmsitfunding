<?php

namespace App\ValueObjects\Zakat;

use App\Support\Money\CurrencyMinorUnits;

/**
 * CR-001-B — structural input to ZakatCalculatorService (docs/change-requests/
 * CR-001-public-experience-cms-ziswaf-admin-v2.md Section 16/20). Carries no
 * business logic or formula — only validated, well-typed data. Money
 * discipline (Section 64): `assetValueMinor` is an integer minor-unit
 * amount; a float is rejected explicitly rather than silently truncated.
 *
 * CODEX-CR001B-04 remediation: a numeric string is converted to `int` only
 * after proving it is exactly representable (string-digit comparison
 * against PHP_INT_MAX — never a float-based bounds check, which would
 * itself lose precision at the boundary it is supposed to guard).
 * `currency` is validated against the project's single canonical registry
 * (App\Support\Money\CurrencyMinorUnits) — no second currency list.
 */
final class CalculationInput
{
    public readonly int $assetValueMinor;

    public readonly string $currency;

    public readonly \DateTimeImmutable $asOfDate;

    public function __construct(mixed $assetValueMinor, string $currency, \DateTimeImmutable $asOfDate)
    {
        if (! CurrencyMinorUnits::isRegistered($currency)) {
            throw new \InvalidArgumentException("CalculationInput currency [{$currency}] is not a registered currency.");
        }

        $this->assetValueMinor = self::toBoundedNonNegativeInt($assetValueMinor, 'CalculationInput assetValueMinor');
        $this->currency = $currency;
        $this->asOfDate = $asOfDate;
    }

    private static function toBoundedNonNegativeInt(mixed $value, string $fieldName): int
    {
        if (is_float($value)) {
            throw new \InvalidArgumentException("{$fieldName} must never be a float; pass an integer or an integer-valued numeric string.");
        }

        if (is_int($value)) {
            if ($value < 0) {
                throw new \InvalidArgumentException("{$fieldName} must not be negative.");
            }

            return $value;
        }

        // ctype_digit rejects a leading '-', decimal points, and any
        // non-digit character in one check — negative and malformed
        // strings both fail here, never silently coerced.
        if (! is_string($value) || $value === '' || ! ctype_digit($value)) {
            throw new \InvalidArgumentException("{$fieldName} must be a non-negative integer or an integer-valued numeric string.");
        }

        $normalized = ltrim($value, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $max = (string) PHP_INT_MAX;

        if (strlen($normalized) > strlen($max) || (strlen($normalized) === strlen($max) && strcmp($normalized, $max) > 0)) {
            throw new \InvalidArgumentException("{$fieldName} exceeds the maximum representable integer value.");
        }

        return (int) $value;
    }
}
