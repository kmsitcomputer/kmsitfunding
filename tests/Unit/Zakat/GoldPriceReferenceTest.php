<?php

namespace Tests\Unit\Zakat;

use App\Models\Zakat\GoldPriceReference;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CR-001-B (Schema #8) — GoldPriceReference admin-entered snapshot coverage.
 * `amount_minor` is asserted to be stored/returned as a plain integer,
 * never a float (Section 64 money discipline).
 */
class GoldPriceReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_valid_row_with_integer_amount_minor(): void
    {
        $reference = GoldPriceReference::create([
            'ulid' => (string) Str::ulid(),
            'amount_minor' => 150000000,
            'currency' => 'IDR',
            'as_of_date' => '2026-09-21',
        ]);

        $this->assertIsInt($reference->amount_minor);
        $this->assertSame(150000000, $reference->amount_minor);
        $this->assertNotInstanceOf(\DateTimeInterface::class, $reference->amount_minor);
    }

    public function test_it_rejects_a_duplicate_currency_and_date_pair(): void
    {
        GoldPriceReference::create([
            'ulid' => (string) Str::ulid(), 'amount_minor' => 1, 'currency' => 'IDR', 'as_of_date' => '2026-09-21',
        ]);

        $this->expectException(QueryException::class);
        GoldPriceReference::create([
            'ulid' => (string) Str::ulid(), 'amount_minor' => 2, 'currency' => 'IDR', 'as_of_date' => '2026-09-21',
        ]);
    }
}
