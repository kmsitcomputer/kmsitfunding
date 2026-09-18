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
     * Display-only formatted string. The major/minor split happens ONLY here,
     * at the final render step — never for storage or comparison — and is
     * computed with INTEGER/STRING arithmetic exclusively: HD-IMP007-02
     * makes floating-point never the authoritative representation, and
     * AC-007-021 requires that no floating-point arithmetic be performed at
     * any point in the request lifecycle.
     *
     * Using intdiv()/% plus pure-string thousands grouping (rather than
     * `$amountMinor / 10 ** $digits` and number_format()) also keeps the
     * output EXACT for minor-unit amounts beyond 2**53, where a float-based
     * division would silently lose precision.
     */
    public function format(): string
    {
        $digits = CurrencyMinorUnits::digitsFor($this->currency);

        // amount_minor is unsigned at the database layer and validated
        // non-negative at every write path; the sign branch is purely
        // defensive and uses integer negation only.
        $sign = $this->amountMinor < 0 ? '-' : '';
        $absolute = $sign === '-' ? -$this->amountMinor : $this->amountMinor;

        if ($digits === 0) {
            $major = (string) $absolute;
            $minorPart = '';
        } else {
            $factor = 10 ** $digits;

            $major = (string) intdiv($absolute, $factor);
            $minorPart = str_pad((string) ($absolute % $factor), $digits, '0', STR_PAD_LEFT);
        }

        // Thousands grouping as a pure string operation — no float involved.
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $major);

        $formatted = $minorPart === '' ? $grouped : "{$grouped},{$minorPart}";

        return "{$this->currency} {$sign}{$formatted}";
    }
}
