<?php

namespace Tests\Unit\Fidyah;

use App\Models\Fidyah\FidyahCalculationSnapshot;
use App\Models\Fidyah\FidyahPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CR-001-B (Schema #11) — FidyahCalculationSnapshot immutability coverage,
 * mirroring ZakatCalculationSnapshotTest exactly.
 */
class FidyahCalculationSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function makeSnapshot(): FidyahCalculationSnapshot
    {
        $policy = FidyahPolicy::create([
            'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
            'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        return FidyahCalculationSnapshot::create([
            'ulid' => (string) Str::ulid(),
            'fidyah_policy_id' => $policy->id,
            'input' => ['missedDays' => 3],
            'result' => ['amountDueMinor' => 10500000],
            'computed_at' => now(),
        ]);
    }

    public function test_it_creates_a_valid_row(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->assertSame(['missedDays' => 3], $snapshot->input);
        $this->assertSame(['amountDueMinor' => 10500000], $snapshot->result);
    }

    public function test_it_rejects_an_update_after_creation(): void
    {
        $snapshot = $this->makeSnapshot();

        $snapshot->result = ['amountDueMinor' => 1];

        $this->expectException(\LogicException::class);
        $snapshot->save();
    }
}
