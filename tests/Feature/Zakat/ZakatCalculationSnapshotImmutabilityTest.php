<?php

namespace Tests\Feature\Zakat;

use App\Models\Zakat\GoldPriceReference;
use App\Models\Zakat\NisabPolicy;
use App\Models\Zakat\ZakatCalculationSnapshot;
use App\Models\Zakat\ZakatPolicy;
use App\Models\Zakat\ZakatType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CODEX-CR001B-01 remediation coverage — proves the append-only boundary
 * holds across every supported Eloquent persistence path AND at the raw
 * database layer (trigger-enforced), not merely the previously-covered
 * instance save() path.
 *
 * CODEX-CR001B-03 remediation coverage — proves typed provenance
 * (NisabPolicy/GoldPriceReference) can be persisted, is protected from
 * cascade loss, and is unaffected by later policy/reference versions.
 */
class ZakatCalculationSnapshotImmutabilityTest extends TestCase
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
        $nisab = NisabPolicy::create([
            'ulid' => (string) Str::ulid(), 'basis_code' => 'GOLD_GRAM', 'gram_equivalent' => '85.0000',
            'effective_from' => '2026-01-01', 'status' => 'ACTIVE',
        ]);
        $goldPrice = GoldPriceReference::create([
            'ulid' => (string) Str::ulid(), 'amount_minor' => 150000000, 'currency' => 'IDR', 'as_of_date' => '2026-09-21',
        ]);

        return ZakatCalculationSnapshot::create([
            'ulid' => (string) Str::ulid(),
            'zakat_type_id' => $type->id,
            'zakat_policy_id' => $policy->id,
            'nisab_policy_id' => $nisab->id,
            'gold_price_reference_id' => $goldPrice->id,
            'input' => ['assetValueMinor' => 100000000],
            'result' => ['amountDueMinor' => 2500000],
            'computed_at' => now(),
        ]);
    }

    public function test_create_is_allowed_and_persists_typed_provenance(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->assertNotNull($snapshot->nisab_policy_id);
        $this->assertNotNull($snapshot->gold_price_reference_id);
        $this->assertNotNull($snapshot->nisabPolicy);
        $this->assertNotNull($snapshot->goldPriceReference);
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

    public function test_update_quietly_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalResult = $snapshot->result;

        try {
            $snapshot->updateQuietly(['result' => ['amountDueMinor' => 1]]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalResult, $snapshot->fresh()->result);
    }

    public function test_increment_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalTypeId = $snapshot->zakat_type_id;

        try {
            $snapshot->increment('zakat_type_id');
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalTypeId, $snapshot->fresh()->zakat_type_id);
    }

    public function test_decrement_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalTypeId = $snapshot->zakat_type_id;

        try {
            $snapshot->decrement('zakat_type_id');
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalTypeId, $snapshot->fresh()->zakat_type_id);
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
            ZakatCalculationSnapshot::where('id', $snapshot->id)->update(['computed_at' => now()->addDay()]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalComputedAt, $snapshot->fresh()->computed_at->toDateTimeString());
    }

    public function test_upsert_against_an_existing_row_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalResult = $snapshot->result;

        try {
            ZakatCalculationSnapshot::query()->upsert(
                [['id' => $snapshot->id, 'ulid' => $snapshot->ulid, 'zakat_type_id' => $snapshot->zakat_type_id, 'zakat_policy_id' => $snapshot->zakat_policy_id, 'input' => '{}', 'result' => '{}', 'computed_at' => now()]],
                ['id'],
            );
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, ZakatCalculationSnapshot::query()->count());
        $this->assertSame($originalResult, $snapshot->fresh()->result);
    }

    public function test_instance_delete_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();

        try {
            $snapshot->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(ZakatCalculationSnapshot::find($snapshot->id));
        $this->assertSame(1, ZakatCalculationSnapshot::query()->count());
    }

    public function test_query_builder_delete_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();

        try {
            ZakatCalculationSnapshot::where('id', $snapshot->id)->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(ZakatCalculationSnapshot::find($snapshot->id));
    }

    public function test_query_builder_force_delete_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();

        try {
            ZakatCalculationSnapshot::where('id', $snapshot->id)->forceDelete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(ZakatCalculationSnapshot::find($snapshot->id));
    }

    public function test_query_builder_touch_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalComputedAt = $snapshot->computed_at->toDateTimeString();

        try {
            ZakatCalculationSnapshot::where('id', $snapshot->id)->touch();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalComputedAt, $snapshot->fresh()->computed_at->toDateTimeString());
    }

    public function test_query_builder_increment_each_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalTypeId = $snapshot->zakat_type_id;

        try {
            ZakatCalculationSnapshot::where('id', $snapshot->id)->incrementEach(['zakat_type_id' => 1]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalTypeId, $snapshot->fresh()->zakat_type_id);
    }

    public function test_query_builder_decrement_each_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalTypeId = $snapshot->zakat_type_id;

        try {
            ZakatCalculationSnapshot::where('id', $snapshot->id)->decrementEach(['zakat_type_id' => 1]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalTypeId, $snapshot->fresh()->zakat_type_id);
    }

    public function test_query_builder_update_or_insert_against_an_existing_row_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalComputedAt = $snapshot->computed_at->toDateTimeString();

        try {
            ZakatCalculationSnapshot::query()->updateOrInsert(['id' => $snapshot->id], ['computed_at' => now()->addDay()]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, ZakatCalculationSnapshot::query()->count());
        $this->assertSame($originalComputedAt, $snapshot->fresh()->computed_at->toDateTimeString());
    }

    public function test_update_or_create_against_an_existing_snapshot_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalComputedAt = $snapshot->computed_at->toDateTimeString();

        try {
            ZakatCalculationSnapshot::query()->updateOrCreate(
                ['id' => $snapshot->id],
                ['computed_at' => now()->addDay()],
            );
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, ZakatCalculationSnapshot::query()->count());
        $this->assertSame($originalComputedAt, $snapshot->fresh()->computed_at->toDateTimeString());
    }

    public function test_increment_or_create_against_an_existing_snapshot_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalTypeId = $snapshot->zakat_type_id;

        try {
            ZakatCalculationSnapshot::query()->incrementOrCreate(['id' => $snapshot->id], 'zakat_type_id');
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, ZakatCalculationSnapshot::query()->count());
        $this->assertSame($originalTypeId, $snapshot->fresh()->zakat_type_id);
    }

    public function test_create_via_first_or_create_is_allowed_when_no_row_matches(): void
    {
        $type = ZakatType::create(['ulid' => (string) Str::ulid(), 'code' => 'MAAL-'.uniqid(), 'name' => 'Zakat Maal']);
        $policy = ZakatPolicy::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $snapshot = ZakatCalculationSnapshot::query()->firstOrCreate(
            ['ulid' => (string) Str::ulid()],
            [
                'zakat_type_id' => $type->id, 'zakat_policy_id' => $policy->id,
                'input' => ['a' => 1], 'result' => ['b' => 2], 'computed_at' => now(),
            ],
        );

        $this->assertTrue($snapshot->wasRecentlyCreated);
    }

    public function test_reads_remain_allowed(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->assertSame(1, ZakatCalculationSnapshot::where('id', $snapshot->id)->count());
        $this->assertNotNull(ZakatCalculationSnapshot::find($snapshot->id));
    }

    public function test_deleting_a_referenced_policy_is_denied_by_restrict_on_delete(): void
    {
        $snapshot = $this->makeSnapshot();
        $policy = ZakatPolicy::findOrFail($snapshot->zakat_policy_id);

        $this->expectException(QueryException::class);
        DB::table('zakat_policies')->where('id', $policy->id)->delete();
    }

    public function test_a_direct_database_update_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->expectException(QueryException::class);
        DB::statement('UPDATE zakat_calculation_snapshots SET computed_at = ? WHERE id = ?', [now(), $snapshot->id]);
    }

    public function test_a_direct_database_delete_is_denied(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->expectException(QueryException::class);
        DB::statement('DELETE FROM zakat_calculation_snapshots WHERE id = ?', [$snapshot->id]);
    }

    public function test_a_newer_nisab_policy_version_does_not_alter_an_existing_snapshots_reference(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalNisabId = $snapshot->nisab_policy_id;

        NisabPolicy::create([
            'ulid' => (string) Str::ulid(), 'basis_code' => 'GOLD_GRAM', 'gram_equivalent' => '90.0000',
            'effective_from' => '2027-01-01', 'status' => 'ACTIVE',
        ]);

        $this->assertSame($originalNisabId, $snapshot->fresh()->nisab_policy_id);
    }

    // ------------------------------------------------------------------
    // CODEX-CR001B-01 Round 3 — the toBase()/getQuery() escape hatch.
    // Each denial test also asserts persisted state is byte-for-byte
    // unchanged, not merely that an exception was thrown.
    // ------------------------------------------------------------------

    public function test_to_base_truncate_is_denied_and_rows_remain(): void
    {
        $snapshot = $this->makeSnapshot();

        try {
            ZakatCalculationSnapshot::query()->toBase()->truncate();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, ZakatCalculationSnapshot::query()->count());
        $this->assertNotNull(ZakatCalculationSnapshot::find($snapshot->id));
    }

    public function test_get_query_truncate_is_denied_and_rows_remain(): void
    {
        $this->makeSnapshot();

        try {
            ZakatCalculationSnapshot::query()->getQuery()->truncate();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, ZakatCalculationSnapshot::query()->count());
    }

    public function test_to_base_update_from_is_denied_and_unchanged(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalResult = $snapshot->result;

        try {
            ZakatCalculationSnapshot::query()->where('id', $snapshot->id)->toBase()->updateFrom(['result' => '{"amountDueMinor":1}']);
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
            ZakatCalculationSnapshot::query()->where('id', $snapshot->id)->toBase()->update(['computed_at' => now()->addDay()]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalComputedAt, $snapshot->fresh()->computed_at->toDateTimeString());
    }

    public function test_to_base_delete_is_denied_and_row_exists(): void
    {
        $snapshot = $this->makeSnapshot();

        try {
            ZakatCalculationSnapshot::query()->where('id', $snapshot->id)->toBase()->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(ZakatCalculationSnapshot::find($snapshot->id));
    }

    public function test_get_query_update_is_denied_and_row_unchanged(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalResult = $snapshot->result;

        try {
            ZakatCalculationSnapshot::query()->where('id', $snapshot->id)->getQuery()->update(['result' => '{"amountDueMinor":999}']);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalResult, $snapshot->fresh()->result);
    }

    public function test_get_query_delete_is_denied_and_row_exists(): void
    {
        $snapshot = $this->makeSnapshot();

        try {
            ZakatCalculationSnapshot::query()->where('id', $snapshot->id)->getQuery()->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(ZakatCalculationSnapshot::find($snapshot->id));
    }

    public function test_to_base_increment_is_denied_and_row_unchanged(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalTypeId = $snapshot->zakat_type_id;

        try {
            ZakatCalculationSnapshot::query()->where('id', $snapshot->id)->toBase()->increment('zakat_type_id');
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalTypeId, $snapshot->fresh()->zakat_type_id);
    }

    public function test_to_base_upsert_is_denied_and_row_unchanged(): void
    {
        $snapshot = $this->makeSnapshot();
        $originalResult = $snapshot->result;

        try {
            ZakatCalculationSnapshot::query()->toBase()->upsert(
                [['id' => $snapshot->id, 'result' => '{"amountDueMinor":1}']],
                ['id'],
            );
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalResult, $snapshot->fresh()->result);
    }

    // ------------------------------------------------------------------
    // Positive ORM-compatibility regression — Round 3 touches
    // newBaseQueryBuilder(), so prove ordinary reads/relationships/
    // creation still function exactly as before.
    // ------------------------------------------------------------------

    public function test_ordinary_reads_still_work_after_the_base_query_builder_change(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->assertTrue(ZakatCalculationSnapshot::query()->where('id', $snapshot->id)->exists());
        $this->assertSame(1, ZakatCalculationSnapshot::query()->count());
        $this->assertNotNull(ZakatCalculationSnapshot::query()->where('id', $snapshot->id)->first());
        $this->assertNotNull(ZakatCalculationSnapshot::find($snapshot->id));
        $this->assertCount(1, ZakatCalculationSnapshot::query()->get());
    }

    public function test_relationship_reads_still_work_after_the_base_query_builder_change(): void
    {
        $snapshot = $this->makeSnapshot();

        $this->assertNotNull($snapshot->zakatType);
        $this->assertNotNull($snapshot->zakatPolicy);
        $this->assertNotNull($snapshot->nisabPolicy);
        $this->assertNotNull($snapshot->goldPriceReference);

        $eagerLoaded = ZakatCalculationSnapshot::with(['zakatType', 'zakatPolicy'])->find($snapshot->id);
        $this->assertTrue($eagerLoaded->relationLoaded('zakatType'));
        $this->assertTrue($eagerLoaded->relationLoaded('zakatPolicy'));
    }

    public function test_create_still_works_after_the_base_query_builder_change(): void
    {
        $type = ZakatType::create(['ulid' => (string) Str::ulid(), 'code' => 'MAAL-'.uniqid(), 'name' => 'Zakat Maal']);
        $policy = ZakatPolicy::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $snapshot = ZakatCalculationSnapshot::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'zakat_policy_id' => $policy->id,
            'input' => ['a' => 1], 'result' => ['b' => 2], 'computed_at' => now(),
        ]);

        $this->assertTrue($snapshot->exists);
        $this->assertNotNull($snapshot->id);
    }
}
