<?php

namespace App\ValueObjects\Fidyah;

/**
 * CR-001-B — structural input to FidyahCalculatorService (docs/change-requests/
 * CR-001-public-experience-cms-ziswaf-admin-v2.md Section 16/20). Carries no
 * business logic or formula — only validated, well-typed data.
 * `missedDays` is a plain count, never a float.
 *
 * CODEX-CR001B-04 remediation: overflow-safe integer parsing (see
 * App\ValueObjects\Zakat\CalculationInput for the identical technique). No
 * religious maximum day count is invented — only PHP integer
 * representability and non-negativity are enforced.
 */
final class CalculationInput
{
    public readonly int $missedDays;

    public readonly \DateTimeImmutable $asOfDate;

    public function __construct(mixed $missedDays, \DateTimeImmutable $asOfDate)
    {
        $this->missedDays = self::toBoundedNonNegativeInt($missedDays, 'CalculationInput missedDays');
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
