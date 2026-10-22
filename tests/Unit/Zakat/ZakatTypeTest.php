<?php

namespace Tests\Unit\Zakat;

use App\Models\Zakat\ZakatType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CR-001-B (Schema #5) — ZakatType registry model coverage.
 */
class ZakatTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_valid_row_with_expected_defaults(): void
    {
        $type = ZakatType::create([
            'ulid' => (string) Str::ulid(),
            'code' => 'MAAL',
            'name' => 'Zakat Maal',
        ])->refresh();

        $this->assertTrue($type->is_active);
        $this->assertSame('MAAL', $type->code);
        $this->assertNotEmpty($type->ulid);
    }

    public function test_it_rejects_a_duplicate_code(): void
    {
        ZakatType::create(['ulid' => (string) Str::ulid(), 'code' => 'MAAL', 'name' => 'Zakat Maal']);

        $this->expectException(QueryException::class);
        ZakatType::create(['ulid' => (string) Str::ulid(), 'code' => 'MAAL', 'name' => 'Duplicate']);
    }
}
