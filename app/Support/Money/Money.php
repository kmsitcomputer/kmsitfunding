<?php

namespace App\Support\Money;

/**
 * IMP-007 (HD-IMP007-02) — the canonical Money value object: an immutable
 * integer minor-unit amount + ISO 4217 currency code. No arithmetic
 * operations are implemented (the minimum foundation IMP-007 needs for
 * Campaign.target_amount_minor display/storage — see
 * docs/implementation/IMP-007-campaign-program-fund.md section 8a). No
 * floating-point value is ever the authoritative representation.
 */
final class Money
{
    private function __construct(
        private readonly int $amountMinor,
        private readonly string $currency,
    ) {}

    public static function ofMinorUnits(int $amountMinor, string $currency): self
    {
        // Fail-closed on an unregistered currency at construction time —
        // never silently assume a digit count.
        CurrencyMinorUnits::digitsFor($currency);

        return new self($amountMinor, $currency);
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function equals(Money $other): bool
    {
        return $this->amountMinor === $other->amountMinor && $this->currency === $other->currency;
    }

    /**
     * Display-only formatted string. Division happens ONLY here, at the
     * final render step — never for storage or comparison.
     */
    public function format(): string
    {
        $digits = CurrencyMinorUnits::digitsFor($this->currency);
        $major = $this->amountMinor / (10 ** $digits);

        $formatted = $digits > 0
            ? number_format($major, $digits, ',', '.')
            : number_format($major, 0, ',', '.');

        return "{$this->currency} {$formatted}";
    }
}
