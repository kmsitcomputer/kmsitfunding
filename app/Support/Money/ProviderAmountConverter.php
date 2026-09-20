<?php

namespace App\Support\Money;

use App\Services\Payment\Exceptions\PaymentValidationException;

/**
 * IMP-009 HD-IMP009-13 — canonical Money / provider unit conversion
 * (docs/implementation/IMP-009-payment-hub.md "Money / Currency").
 *
 * Canonical internal representation is ALWAYS amount_minor +
 * CurrencyMinorUnits (locked IMP-007 contract; IDR digits = 2, so
 * Rp 10,000 = 1,000,000 canonical amount_minor). Provider adapters
 * MUST convert explicitly at the provider boundary — never compare
 * cross-unit amounts, never assume Tripay == Xendit == Stripe ==
 * canonical.
 *
 * Verified provider-unit contracts (official provider documentation,
 * verified 2026-09-20 — see each adapter's docblock for sources):
 * - tripay: whole IDR integers (create `amount`, callback
 *   `total_amount`); IDR-only.
 * - xendit (PaymentRequest API): MAJOR units (`request_amount`, e.g.
 *   100000 for Rp 100.000).
 * - stripe (PaymentIntent): SMALLEST currency unit (sen for IDR —
 *   IDR is NOT a Stripe zero-decimal currency; JPY is, KWD uses
 *   three decimals).
 * - manual_transfer: no provider — canonical units throughout.
 *
 * Integer/decimal-safe arithmetic ONLY: intdiv plus exact-divisibility
 * guards. A canonical value a provider unit cannot represent exactly
 * is a typed rejection, never silent rounding, never float math.
 */
final class ProviderAmountConverter
{
    /**
     * @var array<string>
     */
    private const STRIPE_ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    /**
     * @var array<string>
     */
    private const STRIPE_THREE_DECIMAL = ['BHD', 'JOD', 'KWD', 'OMD', 'TND'];

    public static function toProviderUnits(int $canonicalMinor, string $currency, string $provider): int
    {
        if ($canonicalMinor <= 0) {
            throw new PaymentValidationException('invalid_amount', 'amount_minor must be a positive integer.');
        }

        $canonicalDigits = CurrencyMinorUnits::digitsFor($currency);

        return match ($provider) {
            'manual_transfer' => $canonicalMinor,
            'tripay' => self::toMajorUnits($canonicalMinor, $currency, $canonicalDigits, 'tripay', ['IDR']),
            'xendit' => self::toMajorUnits($canonicalMinor, $currency, $canonicalDigits, 'xendit', null),
            'stripe' => self::toStripeUnits($canonicalMinor, $currency, $canonicalDigits),
            default => throw new PaymentValidationException('invalid_provider', "Provider '{$provider}' is not an approved payment provider."),
        };
    }

    public static function toCanonicalMinor(int $providerAmount, string $currency, string $provider): int
    {
        if ($providerAmount <= 0) {
            throw new PaymentValidationException('invalid_amount', 'The provider-reported amount must be a positive integer.');
        }

        $canonicalDigits = CurrencyMinorUnits::digitsFor($currency);

        return match ($provider) {
            'manual_transfer' => $providerAmount,
            'tripay' => self::fromMajorUnits($providerAmount, $currency, $canonicalDigits, 'tripay', ['IDR']),
            'xendit' => self::fromMajorUnits($providerAmount, $currency, $canonicalDigits, 'xendit', null),
            'stripe' => self::fromStripeUnits($providerAmount, $currency, $canonicalDigits),
            default => throw new PaymentValidationException('invalid_provider', "Provider '{$provider}' is not an approved payment provider."),
        };
    }

    public static function stripeDecimalsFor(string $currency): int
    {
        $upper = strtoupper($currency);

        if (in_array($upper, self::STRIPE_ZERO_DECIMAL, true)) {
            return 0;
        }

        if (in_array($upper, self::STRIPE_THREE_DECIMAL, true)) {
            return 3;
        }

        return 2;
    }

    /**
     * @param  array<string>|null  $allowedCurrencies  null means every registered currency is representable
     */
    private static function toMajorUnits(int $canonicalMinor, string $currency, int $canonicalDigits, string $provider, ?array $allowedCurrencies): int
    {
        if ($allowedCurrencies !== null && ! in_array($currency, $allowedCurrencies, true)) {
            throw new PaymentValidationException(
                'provider_currency_unsupported',
                "Provider '{$provider}' does not support currency '{$currency}'."
            );
        }

        $divisor = 10 ** $canonicalDigits;

        if ($canonicalMinor % $divisor !== 0) {
            throw new PaymentValidationException(
                'provider_amount_not_representable',
                "Amount {$canonicalMinor} ({$currency}) is not exactly representable in provider '{$provider}' units."
            );
        }

        return intdiv($canonicalMinor, $divisor);
    }

    /**
     * @param  array<string>|null  $allowedCurrencies
     */
    private static function fromMajorUnits(int $providerAmount, string $currency, int $canonicalDigits, string $provider, ?array $allowedCurrencies): int
    {
        if ($allowedCurrencies !== null && ! in_array($currency, $allowedCurrencies, true)) {
            throw new PaymentValidationException(
                'provider_currency_unsupported',
                "Provider '{$provider}' does not support currency '{$currency}'."
            );
        }

        return $providerAmount * (10 ** $canonicalDigits);
    }

    private static function toStripeUnits(int $canonicalMinor, string $currency, int $canonicalDigits): int
    {
        $stripeDigits = self::stripeDecimalsFor($currency);
        $diff = $stripeDigits - $canonicalDigits;

        if ($diff >= 0) {
            return $canonicalMinor * (10 ** $diff);
        }

        $divisor = 10 ** (-$diff);

        if ($canonicalMinor % $divisor !== 0) {
            throw new PaymentValidationException(
                'provider_amount_not_representable',
                "Amount {$canonicalMinor} ({$currency}) is not exactly representable in provider 'stripe' units."
            );
        }

        return intdiv($canonicalMinor, $divisor);
    }

    private static function fromStripeUnits(int $providerAmount, string $currency, int $canonicalDigits): int
    {
        $stripeDigits = self::stripeDecimalsFor($currency);
        $diff = $canonicalDigits - $stripeDigits;

        if ($diff >= 0) {
            return $providerAmount * (10 ** $diff);
        }

        $divisor = 10 ** (-$diff);

        if ($providerAmount % $divisor !== 0) {
            throw new PaymentValidationException(
                'provider_amount_not_representable',
                "Amount {$providerAmount} ({$currency}) is not exactly representable in canonical units."
            );
        }

        return intdiv($providerAmount, $divisor);
    }
}
