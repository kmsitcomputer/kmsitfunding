<?php

namespace Tests\Feature\Zakat;

use App\Models\PolicyLeadTimeConfig;
use App\Models\Zakat\ZakatPolicy;
use App\Models\Zakat\ZakatType;
use App\Services\Zakat\ZakatCalculatorService;
use App\Services\Zakat\ZakatPolicyVersioningService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CODEX-CR001B-02 remediation coverage — the authoritative ZakatPolicy
 * write boundary: historical immutability, effective-range validation,
 * overlap prevention, lead-time fail-closed behavior, and new-version
 * correction.
 */
class ZakatPolicyVersioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeType(): ZakatType
    {
        return ZakatType::create(['ulid' => (string) Str::ulid(), 'code' => 'MAAL-'.uniqid(), 'name' => 'Zakat Maal']);
    }

    private function service(): ZakatPolicyVersioningService
    {
        return app(ZakatPolicyVersioningService::class);
    }

    public function test_publishing_a_draft_version_is_allowed_without_a_configured_lead_time(): void
    {
        $type = $this->makeType();

        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
            'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $this->assertSame(1, $policy->version);
        $this->assertSame('DRAFT', $policy->status);
    }

    public function test_activating_a_policy_with_an_unconfigured_lead_time_fails_closed(): void
    {
        $type = $this->makeType();

        try {
            $this->service()->publishNewVersion($type, [
                'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
                'effective_from' => Carbon::today()->addYears(5)->toDateString(), 'status' => 'ACTIVE',
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, ZakatPolicy::where('zakat_type_id', $type->id)->where('status', 'ACTIVE')->count());
        $this->assertSame(0, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    public function test_effective_from_before_the_configured_lead_time_boundary_is_denied(): void
    {
        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 14])->save();

        try {
            $this->service()->publishNewVersion($type, [
                'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
                'effective_from' => Carbon::today()->addDays(13)->toDateString(), 'status' => 'ACTIVE',
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, ZakatPolicy::where('zakat_type_id', $type->id)->where('status', 'ACTIVE')->count());
        $this->assertSame(0, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    public function test_effective_from_exactly_at_the_lead_time_boundary_is_allowed(): void
    {
        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 14])->save();

        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
            'effective_from' => Carbon::today()->addDays(14)->toDateString(), 'status' => 'ACTIVE',
        ]);

        $this->assertSame('ACTIVE', $policy->status);
    }

    public function test_effective_from_after_the_lead_time_boundary_is_allowed(): void
    {
        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 14])->save();

        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
            'effective_from' => Carbon::today()->addDays(30)->toDateString(), 'status' => 'ACTIVE',
        ]);

        $this->assertSame('ACTIVE', $policy->status);
    }

    public function test_an_invalid_effective_range_is_denied(): void
    {
        $type = $this->makeType();

        try {
            $this->service()->publishNewVersion($type, [
                'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
                'effective_from' => '2026-06-01', 'effective_until' => '2026-01-01', 'status' => 'DRAFT',
            ]);
            $this->fail('Expected an InvalidArgumentException to be thrown.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(0, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    public function test_an_overlapping_active_policy_is_denied(): void
    {
        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();

        $first = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
            'effective_from' => Carbon::today()->toDateString(), 'effective_until' => Carbon::today()->addDays(90)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        try {
            $this->service()->publishNewVersion($type, [
                'rate' => '0.030000', 'nisab_basis' => 'GOLD_85G',
                'effective_from' => Carbon::today()->addDays(30)->toDateString(), 'status' => 'ACTIVE',
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        // Existing history is unchanged, and no second ACTIVE row for
        // this type was left behind by the failed attempt.
        $this->assertSame('0.025000', (string) $first->fresh()->rate);
        $this->assertSame(1, ZakatPolicy::where('zakat_type_id', $type->id)->where('status', 'ACTIVE')->count());
        $this->assertSame(1, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    public function test_a_non_overlapping_successor_after_the_first_range_ends_is_allowed(): void
    {
        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();

        $first = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
            'effective_from' => Carbon::today()->toDateString(), 'effective_until' => Carbon::today()->addDays(90)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $second = $this->service()->publishNewVersion($type, [
            'rate' => '0.030000', 'nisab_basis' => 'GOLD_85G',
            'effective_from' => Carbon::today()->addDays(91)->toDateString(), 'status' => 'ACTIVE',
        ]);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
    }

    public function test_a_same_effective_date_conflict_is_denied_at_the_database_layer(): void
    {
        $type = $this->makeType();

        ZakatPolicy::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        try {
            ZakatPolicy::create([
                'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 2,
                'rate' => '0.030000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
            ]);
            $this->fail('Expected a QueryException to be thrown.');
        } catch (QueryException) {
        }

        $this->assertSame(1, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    public function test_a_published_policy_cannot_be_updated(): void
    {
        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();
        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => Carbon::today()->toDateString(), 'status' => 'ACTIVE',
        ]);

        try {
            $policy->forceFill(['rate' => '0.999999'])->save();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame('0.025000', (string) $policy->fresh()->rate);
    }

    public function test_an_ordinary_status_flip_to_active_via_save_is_denied(): void
    {
        $type = $this->makeType();
        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        try {
            $policy->forceFill(['status' => 'ACTIVE'])->save();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame('DRAFT', $policy->fresh()->status);
    }

    public function test_an_ordinary_status_update_to_active_via_the_query_builder_is_denied(): void
    {
        $type = $this->makeType();
        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        try {
            ZakatPolicy::where('id', $policy->id)->update(['status' => 'ACTIVE']);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame('DRAFT', $policy->fresh()->status);
    }

    public function test_direct_active_create_bypassing_the_service_is_denied(): void
    {
        $type = $this->makeType();

        try {
            ZakatPolicy::create([
                'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
                'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'ACTIVE',
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    public function test_direct_draft_create_bypassing_the_service_is_allowed(): void
    {
        $type = $this->makeType();

        $policy = ZakatPolicy::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $this->assertSame('DRAFT', $policy->status);
    }

    public function test_an_ordinary_edit_of_a_draft_row_that_does_not_touch_status_is_allowed(): void
    {
        $type = $this->makeType();
        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $policy->forceFill(['rate' => '0.030000'])->save();

        $this->assertSame('0.030000', (string) $policy->fresh()->rate);
    }

    public function test_a_historical_policy_builder_update_is_denied(): void
    {
        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();
        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => Carbon::today()->toDateString(), 'status' => 'ACTIVE',
        ]);
        $originalSourceRef = $policy->source_ref;

        try {
            ZakatPolicy::where('id', $policy->id)->update(['source_ref' => 'irrelevant']);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalSourceRef, $policy->fresh()->source_ref);
    }

    public function test_a_historical_policy_builder_delete_is_denied(): void
    {
        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();
        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => Carbon::today()->toDateString(), 'status' => 'ACTIVE',
        ]);

        try {
            ZakatPolicy::where('id', $policy->id)->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(ZakatPolicy::find($policy->id));
    }

    public function test_an_invalid_effective_range_via_direct_persistence_is_denied(): void
    {
        $type = $this->makeType();

        try {
            ZakatPolicy::create([
                'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
                'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
                'effective_from' => '2026-06-01', 'effective_until' => '2026-01-01', 'status' => 'DRAFT',
            ]);
            $this->fail('Expected an InvalidArgumentException to be thrown.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(0, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    public function test_ambiguous_resolver_data_fails_closed(): void
    {
        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();

        // Simulate corrupted/ambiguous history the resolver must never
        // arbitrate: two ACTIVE rows for the same type and overlapping
        // date, inserted via the raw base query builder (the one
        // documented, out-of-scope bypass this finding does not attempt
        // to prevent) rather than through the governed model.
        DB::table('zakat_policies')->insert([
            [
                'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
                'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01',
                'effective_until' => null, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 2,
                'rate' => '0.030000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-02',
                'effective_until' => null, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        try {
            app(ZakatCalculatorService::class)->resolveApplicablePolicy($type, new \DateTimeImmutable('2026-06-01'));
            $this->fail('Expected a RuntimeException to be thrown.');
        } catch (\RuntimeException) {
        }

        // The resolver is read-only: the ambiguous history it refused to
        // arbitrate is still exactly as corrupted (and un-mutated) as it
        // was inserted.
        $this->assertSame(2, ZakatPolicy::where('zakat_type_id', $type->id)->where('status', 'ACTIVE')->count());
    }

    public function test_a_published_policy_cannot_be_deleted(): void
    {
        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();
        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => Carbon::today()->toDateString(), 'status' => 'ACTIVE',
        ]);

        try {
            $policy->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(ZakatPolicy::find($policy->id));
    }

    public function test_a_draft_policy_can_still_be_edited_and_deleted(): void
    {
        $type = $this->makeType();
        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $policy->forceFill(['rate' => '0.030000'])->save();
        $this->assertSame('0.030000', (string) $policy->rate);

        $this->assertTrue($policy->delete());
    }

    public function test_a_successor_version_does_not_alter_the_historical_row(): void
    {
        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();

        $first = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
            'effective_from' => Carbon::today()->toDateString(), 'effective_until' => Carbon::today()->addDays(90)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $this->service()->publishNewVersion($type, [
            'rate' => '0.030000', 'nisab_basis' => 'GOLD_85G',
            'effective_from' => Carbon::today()->addDays(91)->toDateString(), 'status' => 'ACTIVE',
        ]);

        $this->assertSame('0.025000', (string) $first->fresh()->rate);
    }

    public function test_concurrent_overlapping_publish_attempts_yield_only_one_successful_outcome(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Real cross-connection lock contention requires the disposable MySQL connection.');
        }

        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();

        $lockKey = "zakat_policy_type_{$type->id}";

        // Simulate a concurrent holder of the same advisory lock on a
        // genuinely SEPARATE physical connection (MySQL GET_LOCK is
        // per-connection — reusing the app's own PDO would just
        // re-acquire the same lock, proving nothing) — proving the
        // service fails closed instead of proceeding blind when it
        // cannot acquire the lock.
        $config = config('database.connections.'.config('database.default'));
        $secondConnection = new \PDO(
            "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
            $config['username'],
            $config['password'],
        );
        $stmt = $secondConnection->prepare('SELECT GET_LOCK(?, 1)');
        $stmt->execute([$lockKey]);
        $this->assertSame(1, (int) $stmt->fetchColumn());

        try {
            try {
                $this->service()->publishNewVersion($type, [
                    'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
                    'effective_from' => Carbon::today()->toDateString(), 'status' => 'ACTIVE',
                ]);
                $this->fail('Expected a RuntimeException to be thrown.');
            } catch (\RuntimeException) {
            }
        } finally {
            $stmt = $secondConnection->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$lockKey]);
        }

        // The failed attempt (blocked by the concurrent lock holder)
        // left no partial/ambiguous row behind for this type.
        $this->assertSame(0, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    // ------------------------------------------------------------------
    // CODEX-CR001B-02 Round 3 — forwarded low-level insert bypass and the
    // toBase()/getQuery() escape hatch. Every denial also asserts no
    // ACTIVE row exists / the DRAFT row is untouched, not merely that an
    // exception was thrown.
    // ------------------------------------------------------------------

    public function test_forwarded_insert_with_active_status_is_denied_and_creates_nothing(): void
    {
        $type = $this->makeType();

        try {
            ZakatPolicy::query()->insert([
                'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
                'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01',
                'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    public function test_forwarded_insert_get_id_with_active_status_is_denied_and_creates_nothing(): void
    {
        $type = $this->makeType();

        try {
            ZakatPolicy::query()->insertGetId([
                'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
                'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01',
                'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    public function test_forwarded_insert_or_ignore_with_active_status_is_denied_and_creates_nothing(): void
    {
        $type = $this->makeType();

        try {
            ZakatPolicy::query()->insertOrIgnore([
                'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
                'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01',
                'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    /**
     * CODEX-CR001B-02 Round 4 (R4-02): proves
     * GovernedPolicyQueryBuilder::insertOrIgnoreReturning()'s corrected
     * signature is actually callable and behaves correctly end-to-end —
     * not merely that its declared types now match the installed Laravel
     * 13.31.0 parent. SQLite's grammar implements `RETURNING`; MySQL's
     * does not (it throws its own RuntimeException regardless of this
     * governance layer) — this test therefore only runs where the
     * feature is actually supported.
     */
    public function test_insert_or_ignore_returning_with_draft_status_returns_a_collection_and_persists_once(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('insertOrIgnoreReturning is only exercised on SQLite here; MySQL\'s grammar does not implement RETURNING at all.');
        }

        $type = $this->makeType();

        $result = ZakatPolicy::query()->insertOrIgnoreReturning([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01',
            'status' => 'DRAFT', 'created_at' => now(), 'updated_at' => now(),
        ], ['*'], 'ulid');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame(1, ZakatPolicy::where('zakat_type_id', $type->id)->where('status', 'DRAFT')->count());
    }

    public function test_insert_or_ignore_returning_with_active_status_is_denied_before_any_query_runs(): void
    {
        $type = $this->makeType();

        try {
            ZakatPolicy::query()->insertOrIgnoreReturning([
                'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
                'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01',
                'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
            ], ['*'], 'ulid');
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    public function test_forwarded_insert_using_is_denied(): void
    {
        $type = $this->makeType();

        try {
            ZakatPolicy::query()->insertUsing(
                ['ulid', 'zakat_type_id', 'version', 'rate', 'nisab_basis', 'effective_from', 'status', 'created_at', 'updated_at'],
                'select 1',
            );
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    public function test_forwarded_insert_with_draft_status_still_works(): void
    {
        $type = $this->makeType();

        ZakatPolicy::query()->insertGetId([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01',
            'status' => 'DRAFT', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(1, ZakatPolicy::where('zakat_type_id', $type->id)->where('status', 'DRAFT')->count());
    }

    public function test_to_base_truncate_is_denied_and_history_remains(): void
    {
        $type = $this->makeType();
        ZakatPolicy::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        try {
            ZakatPolicy::query()->toBase()->truncate();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    public function test_get_query_truncate_is_denied_and_history_remains(): void
    {
        $type = $this->makeType();
        ZakatPolicy::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        try {
            ZakatPolicy::query()->getQuery()->truncate();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, ZakatPolicy::where('zakat_type_id', $type->id)->count());
    }

    public function test_to_base_active_transition_is_denied_and_draft_remains(): void
    {
        $type = $this->makeType();
        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        try {
            ZakatPolicy::query()->where('id', $policy->id)->toBase()->update(['status' => 'ACTIVE']);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame('DRAFT', $policy->fresh()->status);
    }

    public function test_get_query_active_transition_is_denied_and_draft_remains(): void
    {
        $type = $this->makeType();
        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        try {
            ZakatPolicy::query()->where('id', $policy->id)->getQuery()->update(['status' => 'ACTIVE']);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame('DRAFT', $policy->fresh()->status);
    }

    public function test_to_base_historical_update_is_denied_and_unchanged(): void
    {
        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();
        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => Carbon::today()->toDateString(), 'status' => 'ACTIVE',
        ]);
        $originalRate = (string) $policy->rate;

        try {
            ZakatPolicy::query()->where('id', $policy->id)->toBase()->update(['rate' => '0.999999']);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalRate, (string) $policy->fresh()->rate);
    }

    public function test_get_query_historical_delete_is_denied_and_row_exists(): void
    {
        $type = $this->makeType();
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();
        $policy = $this->service()->publishNewVersion($type, [
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => Carbon::today()->toDateString(), 'status' => 'ACTIVE',
        ]);

        try {
            ZakatPolicy::query()->where('id', $policy->id)->getQuery()->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(ZakatPolicy::find($policy->id));
    }

    // ------------------------------------------------------------------
    // Positive ORM-compatibility regression — Round 3 touches
    // newBaseQueryBuilder(), so prove ordinary reads/DRAFT create/edit
    // still function.
    // ------------------------------------------------------------------

    public function test_ordinary_reads_still_work_after_the_base_query_builder_change(): void
    {
        $type = $this->makeType();
        $policy = ZakatPolicy::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $this->assertTrue(ZakatPolicy::query()->where('id', $policy->id)->exists());
        $this->assertSame(1, ZakatPolicy::query()->where('zakat_type_id', $type->id)->count());
        $this->assertNotNull(ZakatPolicy::find($policy->id));
        $this->assertCount(1, ZakatPolicy::query()->where('zakat_type_id', $type->id)->get());
        $this->assertNotNull($policy->zakatType);
    }
}
