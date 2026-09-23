<?php

namespace Tests\Unit\Zakat;

use App\ValueObjects\Zakat\CalculationResult;
use Tests\TestCase;

/**
 * CR-001-B — CalculationResult (Zakat) structural validation coverage.
 * CODEX-CR001B-03 (explainability fields) + CODEX-CR001B-04 (overflow/
 * currency) remediation coverage.
 */
class ZakatCalculationResultValueObjectTest extends TestCase
{
    public function test_it_constructs_a_valid_result_with_an_integer_amount_due_minor(): void
    {
        $result = new CalculationResult(2500000, 'IDR', 'zakat-policy-ulid-example', new \DateTimeImmutable('2026-09-21'));

        $this->assertIsInt($result->amountDueMinor);
        $this->assertSame(2500000, $result->amountDueMinor);
        $this->assertSame('IDR', $result->currency);
        $this->assertSame('zakat-policy-ulid-example', $result->policyVersionRef);
        $this->assertNull($result->thresholdMet);
        $this->assertNull($result->nisabAmountMinor);
        $this->assertNull($result->basisExplanation);
    }

    public function test_it_accepts_the_explainability_fields_without_computing_them(): void
    {
        $result = new CalculationResult(
            2500000, 'IDR', 'zakat-policy-ulid-example', new \DateTimeImmutable('2026-09-21'),
            thresholdMet: true,
            nisabAmountMinor: 100000000,
            basisExplanation: 'GOLD_85G reference as of 2026-09-21',
        );

        $this->assertTrue($result->thresholdMet);
        $this->assertSame(100000000, $result->nisabAmountMinor);
        $this->assertSame('GOLD_85G reference as of 2026-09-21', $result->basisExplanation);
    }

    public function test_it_rejects_a_float(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationResult(2500000.75, 'IDR', 'ref', new \DateTimeImmutable('2026-09-21'));
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

    public function test_it_rejects_a_negative_nisab_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationResult(0, 'IDR', 'ref', new \DateTimeImmutable('2026-09-21'), nisabAmountMinor: -1);
    }

    public function test_it_rejects_an_unregistered_currency(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CalculationResult(0, 'ZZZ', 'ref', new \DateTimeImmutable('2026-09-21'));
    }
}
