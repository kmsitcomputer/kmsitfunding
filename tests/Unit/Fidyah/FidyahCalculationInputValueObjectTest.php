<?php

namespace Tests\Unit\Fidyah;

use App\ValueObjects\Fidyah\CalculationInput;
use Tests\TestCase;

/**
 * CR-001-B — CalculationInput (Fidyah) structural validation coverage,
 * mirroring the Zakat CalculationInput test pattern exactly.
 * CODEX-CR001B-04 remediation coverage.
 */
class FidyahCalculationInputValueObjectTest extends TestCase
{
    public function test_it_accepts_an_integer_day_count(): void
    {
        $input = new CalculationInput(3, new \DateTimeImmutable('2026-09-21'));

        $this->assertSame(3, $input->missedDays);
    }

    public function test_it_accepts_an_integer_valued_numeric_string(): void
    {
        $input = new CalculationInput('3', new \DateTimeImmutable('2026-09-21'));

        $this->assertSame(3, $input->missedDays);
    }

    public function test_it_rejects_a_float(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationInput(3.5, new \DateTimeImmutable('2026-09-21'));
    }

    public function test_it_accepts_php_int_max_with_exact_round_trip(): void
    {
        $max = (string) PHP_INT_MAX;

        $input = new CalculationInput($max, new \DateTimeImmutable('2026-09-21'));

        $this->assertSame(PHP_INT_MAX, $input->missedDays);
    }

    public function test_it_rejects_a_very_long_numeric_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationInput(str_repeat('9', 40), new \DateTimeImmutable('2026-09-21'));
    }

    public function test_it_rejects_a_negative_day_count(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationInput(-1, new \DateTimeImmutable('2026-09-21'));
    }

    public function test_it_accepts_zero(): void
    {
        $input = new CalculationInput(0, new \DateTimeImmutable('2026-09-21'));

        $this->assertSame(0, $input->missedDays);
    }
}
