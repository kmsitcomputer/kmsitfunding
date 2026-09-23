<?php

namespace Tests\Unit\Zakat;

use App\Models\Zakat\ZakatPolicy;
use App\Models\Zakat\ZakatType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CR-001-B (Schema #6) — ZakatPolicy versioned model coverage. No rate
 * value asserted as "correct" — only that the structural column round-trips
 * exactly as stored (never invented as a real Zakat rate).
 */
class ZakatPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): ZakatType
    {
        return ZakatType::create(['ulid' => (string) Str::ulid(), 'code' => 'MAAL-'.uniqid(), 'name' => 'Zakat Maal']);
    }

    public function test_it_creates_a_valid_versioned_row(): void
    {
        $type = $this->makeType();

        $policy = ZakatPolicy::create([
            'ulid' => (string) Str::ulid(),
            'zakat_type_id' => $type->id,
            'version' => 1,
            'rate' => '0.025000',
            'nisab_basis' => 'GOLD_85G',
            'effective_from' => '2026-01-01',
            'status' => 'DRAFT',
        ]);

        $this->assertSame($type->id, $policy->zakat_type_id);
        $this->assertSame('0.025000', (string) $policy->rate);
        $this->assertTrue($policy->effective_from->isSameDay('2026-01-01'));
        $this->assertNull($policy->effective_until);
    }

    public function test_multiple_versions_may_exist_for_the_same_type(): void
    {
        $type = $this->makeType();

        ZakatPolicy::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
            'effective_from' => '2025-01-01', 'effective_until' => '2025-12-31', 'status' => 'DRAFT',
        ]);
        ZakatPolicy::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 2,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
            'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $this->assertSame(2, $type->policies()->count());
    }
}
