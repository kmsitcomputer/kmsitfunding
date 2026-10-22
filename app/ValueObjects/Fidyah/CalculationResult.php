<?php

namespace App\ValueObjects\Fidyah;

use App\Support\Money\CurrencyMinorUnits;

/**
 * CR-001-B — structural output of FidyahCalculatorService, mirroring
 * App\ValueObjects\Zakat\CalculationResult's validation exactly
 * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
 * Section 16/20). `amountDueMinor` is an integer minor-unit amount per
 * Section 64. No nisab/threshold explainability fields — Fidyah has no
 * nisab concept.
 *
 * CODEX-CR001B-04 remediation: overflow-safe integer parsing and
 * canonical-registry currency validation.
 */
final class CalculationResult
{
    public readonly int $amountDueMinor;

    public readonly string $currency;

    public readonly string $policyVersionRef;

    public readonly \DateTimeImmutable $computedAt;

    public function __construct(mixed $amountDueMinor, string $currency, string $policyVersionRef, \DateTimeImmutable $computedAt)
    {
        if (! CurrencyMinorUnits::isRegistered($currency)) {
            throw new \InvalidArgumentException("CalculationResult currency [{$currency}] is not a registered currency.");
        }

        $this->amountDueMinor = self::toBoundedNonNegativeInt($amountDueMinor, 'CalculationResult amountDueMinor');
        $this->currency = $currency;
        $this->policyVersionRef = $policyVersionRef;
        $this->computedAt = $computedAt;
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
