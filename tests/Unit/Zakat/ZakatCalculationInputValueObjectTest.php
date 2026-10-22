<?php

namespace Tests\Unit\Zakat;

use App\ValueObjects\Zakat\CalculationInput;
use Tests\TestCase;

/**
 * CR-001-B — CalculationInput (Zakat) structural validation coverage.
 * CODEX-CR001B-04 remediation: overflow/negative/currency-registry cases.
 */
class ZakatCalculationInputValueObjectTest extends TestCase
{
    public function test_it_accepts_an_integer_amount(): void
    {
        $input = new CalculationInput(100000000, 'IDR', new \DateTimeImmutable('2026-09-21'));

        $this->assertSame(100000000, $input->assetValueMinor);
        $this->assertSame('IDR', $input->currency);
    }

    public function test_it_accepts_an_integer_valued_numeric_string(): void
    {
        $input = new CalculationInput('100000000', 'IDR', new \DateTimeImmutable('2026-09-21'));

        $this->assertSame(100000000, $input->assetValueMinor);
    }

    public function test_it_rejects_a_float(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationInput(100000000.50, 'IDR', new \DateTimeImmutable('2026-09-21'));
    }

    public function test_it_rejects_a_non_numeric_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationInput('not-a-number', 'IDR', new \DateTimeImmutable('2026-09-21'));
    }

    public function test_it_accepts_php_int_max_as_a_string_with_exact_round_trip(): void
    {
        $max = (string) PHP_INT_MAX;

        $input = new CalculationInput($max, 'IDR', new \DateTimeImmutable('2026-09-21'));

        $this->assertSame(PHP_INT_MAX, $input->assetValueMinor);
        $this->assertSame($max, (string) $input->assetValueMinor);
    }

    public function test_it_rejects_php_int_max_plus_one_as_a_string(): void
    {
        $overflow = self::decimalStringIncrement((string) PHP_INT_MAX);

        $this->expectException(\InvalidArgumentException::class);

        new CalculationInput($overflow, 'IDR', new \DateTimeImmutable('2026-09-21'));
    }

    public function test_it_rejects_a_very_long_numeric_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationInput(str_repeat('9', 40), 'IDR', new \DateTimeImmutable('2026-09-21'));
    }

    public function test_it_rejects_a_negative_integer(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationInput(-1, 'IDR', new \DateTimeImmutable('2026-09-21'));
    }

    public function test_it_rejects_a_negative_numeric_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationInput('-1', 'IDR', new \DateTimeImmutable('2026-09-21'));
    }

    public function test_it_accepts_zero(): void
    {
        $input = new CalculationInput(0, 'IDR', new \DateTimeImmutable('2026-09-21'));

        $this->assertSame(0, $input->assetValueMinor);
    }

    public function test_it_rejects_an_unregistered_currency(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationInput(1000, 'ZZZ', new \DateTimeImmutable('2026-09-21'));
    }

    public function test_it_accepts_a_registered_currency(): void
    {
        $input = new CalculationInput(1000, 'USD', new \DateTimeImmutable('2026-09-21'));

        $this->assertSame('USD', $input->currency);
    }

    private static function decimalStringIncrement(string $decimal): string
    {
        // bcmath fallback in case the extension is unavailable in a given
        // environment — pure string/array arithmetic, no float involved.
        $digits = array_map('intval', str_split($decimal));
        for ($i = count($digits) - 1; $i >= 0; $i--) {
            $digits[$i]++;
            if ($digits[$i] < 10) {
                return implode('', $digits);
            }
            $digits[$i] = 0;
        }

        return '1'.implode('', $digits);
    }
}
