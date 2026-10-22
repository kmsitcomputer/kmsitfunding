<?php

namespace Tests\Unit\Fidyah;

use App\ValueObjects\Fidyah\CalculationResult;
use Tests\TestCase;

/**
 * CR-001-B — CalculationResult (Fidyah) structural validation coverage,
 * mirroring the Zakat CalculationResult test pattern exactly.
 * CODEX-CR001B-04 remediation coverage.
 */
class FidyahCalculationResultValueObjectTest extends TestCase
{
    public function test_it_constructs_a_valid_result(): void
    {
        $result = new CalculationResult(10500000, 'IDR', 'fidyah-policy-ulid-example', new \DateTimeImmutable('2026-09-21'));

        $this->assertIsInt($result->amountDueMinor);
        $this->assertSame(10500000, $result->amountDueMinor);
    }

    public function test_it_rejects_a_float(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationResult(10500000.99, 'IDR', 'ref', new \DateTimeImmutable('2026-09-21'));
    }

    public function test_it_accepts_php_int_max_with_exact_round_trip(): void
    {
        $max = (string) PHP_INT_MAX;

        $result = new CalculationResult($max, 'IDR', 'ref', new \DateTimeImmutable('2026-09-21'));

        $this->assertSame(PHP_INT_MAX, $result->amountDueMinor);
    }

    public function test_it_rejects_a_very_long_numeric_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationResult(str_repeat('9', 40), 'IDR', 'ref', new \DateTimeImmutable('2026-09-21'));
    }

    public function test_it_rejects_a_negative_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationResult(-1, 'IDR', 'ref', new \DateTimeImmutable('2026-09-21'));
    }

    public function test_it_rejects_an_unregistered_currency(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationResult(0, 'ZZZ', 'ref', new \DateTimeImmutable('2026-09-21'));
    }
}
