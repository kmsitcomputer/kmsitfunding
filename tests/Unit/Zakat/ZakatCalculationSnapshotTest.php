<?php

namespace Tests\Unit\Zakat;

use App\Models\Zakat\ZakatCalculationSnapshot;
use App\Models\Zakat\ZakatPolicy;
use App\Models\Zakat\ZakatType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CR-001-B (Schema #9) — ZakatCalculationSnapshot immutability coverage.
 */
class ZakatCalculationSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function makeSnapshot(): ZakatCalculationSnapshot
    {
        $type = ZakatType::create(['ulid' => (string) Str::ulid(), 'code' => 'MAAL-'.uniqid(), 'name' => 'Zakat Maal']);
        $policy = ZakatPolicy::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
            'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        return ZakatCalculationSnapshot::create([
            'ulid' => (string) Str::ulid(),
            'zakat_type_id' => $type->id,
            'zakat_policy_id' => $policy->id,
            'input' => ['assetValueMinor' => 100000000],
            'result' => ['amountDueMinor' => 2500000],
            'computed_at' => now(),
        ]);
    }

    public function test_it_creates_a_valid_row(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->assertSame(['assetValueMinor' => 100000000], $snapshot->input);
        $this->assertSame(['amountDueMinor' => 2500000], $snapshot->result);
        $this->assertNull($snapshot->acting_principal_id);
    }

    public function test_it_rejects_an_update_after_creation(): void
    {
        $snapshot = $this->makeSnapshot();

        $snapshot->result = ['amountDueMinor' => 9999999];

        $this->expectException(\LogicException::class);
        $snapshot->save();
    }
}
