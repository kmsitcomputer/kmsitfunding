<?php

namespace App\ValueObjects\Zakat;

use App\Support\Money\CurrencyMinorUnits;

/**
 * CR-001-B — structural output of ZakatCalculatorService (docs/change-requests/
 * CR-001-public-experience-cms-ziswaf-admin-v2.md Section 16/20). No formula
 * lives here — this is the shape the (future IMP-019) formula populates.
 * `amountDueMinor` is an integer minor-unit amount per Section 64.
 *
 * CODEX-CR001B-03 remediation: `thresholdMet` / `nisabAmountMinor` /
 * `basisExplanation` are STRUCTURAL explainability fields only — nullable,
 * populated by the future authorized calculator (IMP-019). No threshold
 * rule, nisab value, or gold price is decided or invented here.
 *
 * CODEX-CR001B-04 remediation: overflow-safe integer parsing (see
 * App\ValueObjects\Zakat\CalculationInput for the identical technique) and
 * canonical-registry currency validation.
 */
final class CalculationResult
{
    public readonly int $amountDueMinor;

    public readonly string $currency;

    public readonly string $policyVersionRef;

    public readonly \DateTimeImmutable $computedAt;

    public readonly ?bool $thresholdMet;

    public readonly ?int $nisabAmountMinor;

    public readonly ?string $basisExplanation;

    public function __construct(
        mixed $amountDueMinor,
        string $currency,
        string $policyVersionRef,
        \DateTimeImmutable $computedAt,
        ?bool $thresholdMet = null,
        mixed $nisabAmountMinor = null,
        ?string $basisExplanation = null,
    ) {
        if (! CurrencyMinorUnits::isRegistered($currency)) {
            throw new \InvalidArgumentException("CalculationResult currency [{$currency}] is not a registered currency.");
        }

        $this->amountDueMinor = self::toBoundedNonNegativeInt($amountDueMinor, 'CalculationResult amountDueMinor');
        $this->currency = $currency;
        $this->policyVersionRef = $policyVersionRef;
        $this->computedAt = $computedAt;
        $this->thresholdMet = $thresholdMet;
        $this->nisabAmountMinor = $nisabAmountMinor === null
            ? null
            : self::toBoundedNonNegativeInt($nisabAmountMinor, 'CalculationResult nisabAmountMinor');
        $this->basisExplanation = $basisExplanation;
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
