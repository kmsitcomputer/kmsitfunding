<?php

namespace Tests\Feature\Zakat;

use App\Models\Fidyah\FidyahCalculationSnapshot;
use App\Models\Fidyah\FidyahPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CODEX-CR001B-01 remediation coverage for FidyahCalculationSnapshot,
 * mirroring ZakatCalculationSnapshotImmutabilityTest exactly.
 */
class FidyahCalculationSnapshotImmutabilityTest extends TestCase
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

    public function test_create_is_allowed(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->assertNotNull($snapshot->id);
    }

    public function test_instance_save_mutation_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalResult = $snapshot->result;
        $snapshot->result = ['amountDueMinor' => 1];

        try {
            $snapshot->save();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalResult, $snapshot->fresh()->result);
    }

    public function test_update_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalResult = $snapshot->result;

        try {
            $snapshot->update(['result' => ['amountDueMinor' => 1]]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalResult, $snapshot->fresh()->result);
    }

    public function test_increment_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalPolicyId = $snapshot->fidyah_policy_id;

        try {
            $snapshot->increment('fidyah_policy_id');
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalPolicyId, $snapshot->fresh()->fidyah_policy_id);
    }

    public function test_decrement_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalPolicyId = $snapshot->fidyah_policy_id;

        try {
            $snapshot->decrement('fidyah_policy_id');
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalPolicyId, $snapshot->fresh()->fidyah_policy_id);
    }

    public function test_touch_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalComputedAt = $snapshot->computed_at->toDateTimeString();

        try {
            $snapshot->touch();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalComputedAt, $snapshot->fresh()->computed_at->toDateTimeString());
    }

    public function test_query_builder_mass_update_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalComputedAt = $snapshot->computed_at->toDateTimeString();

        try {
            FidyahCalculationSnapshot::where('id', $snapshot->id)->update(['computed_at' => now()->addDay()]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalComputedAt, $snapshot->fresh()->computed_at->toDateTimeString());
    }

    public function test_instance_delete_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();

        try {
            $snapshot->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(FidyahCalculationSnapshot::find($snapshot->id));
    }

    public function test_query_builder_delete_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();

        try {
            FidyahCalculationSnapshot::where('id', $snapshot->id)->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(FidyahCalculationSnapshot::find($snapshot->id));
    }

    public function test_query_builder_force_delete_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();

        try {
            FidyahCalculationSnapshot::where('id', $snapshot->id)->forceDelete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(FidyahCalculationSnapshot::find($snapshot->id));
    }

    public function test_query_builder_touch_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalComputedAt = $snapshot->computed_at->toDateTimeString();

        try {
            FidyahCalculationSnapshot::where('id', $snapshot->id)->touch();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalComputedAt, $snapshot->fresh()->computed_at->toDateTimeString());
    }

    public function test_query_builder_increment_each_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalPolicyId = $snapshot->fidyah_policy_id;

        try {
            FidyahCalculationSnapshot::where('id', $snapshot->id)->incrementEach(['fidyah_policy_id' => 1]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalPolicyId, $snapshot->fresh()->fidyah_policy_id);
    }

    public function test_query_builder_decrement_each_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalPolicyId = $snapshot->fidyah_policy_id;

        try {
            FidyahCalculationSnapshot::where('id', $snapshot->id)->decrementEach(['fidyah_policy_id' => 1]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalPolicyId, $snapshot->fresh()->fidyah_policy_id);
    }

    public function test_query_builder_update_or_insert_against_an_existing_row_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalComputedAt = $snapshot->computed_at->toDateTimeString();

        try {
            FidyahCalculationSnapshot::query()->updateOrInsert(['id' => $snapshot->id], ['computed_at' => now()->addDay()]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, FidyahCalculationSnapshot::query()->count());
        $this->assertSame($originalComputedAt, $snapshot->fresh()->computed_at->toDateTimeString());
    }

    public function test_update_or_create_against_an_existing_snapshot_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalComputedAt = $snapshot->computed_at->toDateTimeString();

        try {
            FidyahCalculationSnapshot::query()->updateOrCreate(
                ['id' => $snapshot->id],
                ['computed_at' => now()->addDay()],
            );
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, FidyahCalculationSnapshot::query()->count());
        $this->assertSame($originalComputedAt, $snapshot->fresh()->computed_at->toDateTimeString());
    }

    public function test_reads_remain_allowed(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->assertSame(1, FidyahCalculationSnapshot::where('id', $snapshot->id)->count());
        $this->assertNotNull(FidyahCalculationSnapshot::find($snapshot->id));
    }

    public function test_a_direct_database_update_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->expectException(QueryException::class);
        DB::statement('UPDATE fidyah_calculation_snapshots SET computed_at = ? WHERE id = ?', [now(), $snapshot->id]);
    }

    public function test_a_direct_database_delete_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->expectException(QueryException::class);
        DB::statement('DELETE FROM fidyah_calculation_snapshots WHERE id = ?', [$snapshot->id]);
    }

    // ------------------------------------------------------------------
    // CODEX-CR001B-01 Round 3 — the toBase()/getQuery() escape hatch,
    // mirroring ZakatCalculationSnapshotImmutabilityTest exactly.
    // ------------------------------------------------------------------

    public function test_to_base_truncate_is_denied_and_rows_remain(): void
    {
        $this->makeSnapshot();

        try {
            FidyahCalculationSnapshot::query()->toBase()->truncate();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, FidyahCalculationSnapshot::query()->count());
    }

    public function test_get_query_truncate_is_denied_and_rows_remain(): void
    {
        $this->makeSnapshot();

        try {
            FidyahCalculationSnapshot::query()->getQuery()->truncate();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, FidyahCalculationSnapshot::query()->count());
    }

    public function test_to_base_update_from_is_denied_and_unchanged(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalResult = $snapshot->result;

        try {
            FidyahCalculationSnapshot::query()->where('id', $snapshot->id)->toBase()->updateFrom(['result' => '{"amountDueMinor":1}']);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalResult, $snapshot->fresh()->result);
    }

    public function test_to_base_update_is_denied_and_row_unchanged(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalComputedAt = $snapshot->computed_at->toDateTimeString();

        try {
            FidyahCalculationSnapshot::query()->where('id', $snapshot->id)->toBase()->update(['computed_at' => now()->addDay()]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalComputedAt, $snapshot->fresh()->computed_at->toDateTimeString());
    }

    public function test_to_base_delete_is_denied_and_row_exists(): void
    {
        $snapshot = $this->makeSnapshot();

        try {
            FidyahCalculationSnapshot::query()->where('id', $snapshot->id)->toBase()->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(FidyahCalculationSnapshot::find($snapshot->id));
    }

    public function test_get_query_update_is_denied_and_row_unchanged(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalResult = $snapshot->result;

        try {
            FidyahCalculationSnapshot::query()->where('id', $snapshot->id)->getQuery()->update(['result' => '{"amountDueMinor":999}']);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalResult, $snapshot->fresh()->result);
    }

    public function test_get_query_delete_is_denied_and_row_exists(): void
    {
        $snapshot = $this->makeSnapshot();

        try {
            FidyahCalculationSnapshot::query()->where('id', $snapshot->id)->getQuery()->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(FidyahCalculationSnapshot::find($snapshot->id));
    }

    public function test_ordinary_reads_still_work_after_the_base_query_builder_change(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->assertTrue(FidyahCalculationSnapshot::query()->where('id', $snapshot->id)->exists());
        $this->assertSame(1, FidyahCalculationSnapshot::query()->count());
        $this->assertNotNull(FidyahCalculationSnapshot::query()->where('id', $snapshot->id)->first());
        $this->assertCount(1, FidyahCalculationSnapshot::query()->get());
    }

    public function test_relationship_reads_still_work_after_the_base_query_builder_change(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->assertNotNull($snapshot->fidyahPolicy);

        $eagerLoaded = FidyahCalculationSnapshot::with('fidyahPolicy')->find($snapshot->id);
        $this->assertTrue($eagerLoaded->relationLoaded('fidyahPolicy'));
    }
}
