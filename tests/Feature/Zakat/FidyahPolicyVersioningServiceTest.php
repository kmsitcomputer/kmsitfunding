<?php

namespace Tests\Feature\Zakat;

use App\Models\Fidyah\FidyahPolicy;
use App\Models\PolicyLeadTimeConfig;
use App\Services\Fidyah\FidyahCalculatorService;
use App\Services\Fidyah\FidyahPolicyVersioningService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CODEX-CR001B-02 remediation coverage for FidyahPolicy, mirroring
 * ZakatPolicyVersioningServiceTest exactly (org-wide, no type dimension).
 */
class FidyahPolicyVersioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FidyahPolicyVersioningService
    {
        return app(FidyahPolicyVersioningService::class);
    }

    public function test_publishing_a_draft_version_is_allowed_without_a_configured_lead_time(): void
    {
        $policy = $this->service()->publishNewVersion([
            'rate_amount_minor' => 3500000, 'currency' => 'IDR',
            'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $this->assertSame(1, $policy->version);
    }

    public function test_activating_a_policy_with_an_unconfigured_lead_time_fails_closed(): void
    {
        try {
            $this->service()->publishNewVersion([
                'rate_amount_minor' => 3500000, 'currency' => 'IDR',
                'effective_from' => Carbon::today()->addYears(5)->toDateString(), 'status' => 'ACTIVE',
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, FidyahPolicy::count());
    }

    public function test_effective_from_before_the_lead_time_boundary_is_denied(): void
    {
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 14])->save();

        try {
            $this->service()->publishNewVersion([
                'rate_amount_minor' => 3500000, 'currency' => 'IDR',
                'effective_from' => Carbon::today()->addDays(13)->toDateString(), 'status' => 'ACTIVE',
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, FidyahPolicy::count());
    }

    public function test_effective_from_exactly_at_the_lead_time_boundary_is_allowed(): void
    {
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 14])->save();

        $policy = $this->service()->publishNewVersion([
            'rate_amount_minor' => 3500000, 'currency' => 'IDR',
            'effective_from' => Carbon::today()->addDays(14)->toDateString(), 'status' => 'ACTIVE',
        ]);

        $this->assertSame('ACTIVE', $policy->status);
    }

    public function test_an_invalid_effective_range_is_denied(): void
    {
        try {
            $this->service()->publishNewVersion([
                'rate_amount_minor' => 3500000, 'currency' => 'IDR',
                'effective_from' => '2026-06-01', 'effective_until' => '2026-01-01', 'status' => 'DRAFT',
            ]);
            $this->fail('Expected an InvalidArgumentException to be thrown.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(0, FidyahPolicy::count());
    }

    public function test_an_overlapping_active_policy_is_denied(): void
    {
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();

        $first = $this->service()->publishNewVersion([
            'rate_amount_minor' => 3500000, 'currency' => 'IDR',
            'effective_from' => Carbon::today()->toDateString(), 'effective_until' => Carbon::today()->addDays(90)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        try {
            $this->service()->publishNewVersion([
                'rate_amount_minor' => 4000000, 'currency' => 'IDR',
                'effective_from' => Carbon::today()->addDays(30)->toDateString(), 'status' => 'ACTIVE',
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(3500000, $first->fresh()->rate_amount_minor);
        $this->assertSame(1, FidyahPolicy::where('status', 'ACTIVE')->count());
        $this->assertSame(1, FidyahPolicy::count());
    }

    public function test_a_same_effective_date_conflict_is_denied_at_the_database_layer(): void
    {
        FidyahPolicy::create([
            'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000,
            'currency' => 'IDR', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        try {
            FidyahPolicy::create([
                'ulid' => (string) Str::ulid(), 'version' => 2, 'rate_amount_minor' => 4000000,
                'currency' => 'IDR', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
            ]);
            $this->fail('Expected a QueryException to be thrown.');
        } catch (QueryException) {
        }

        $this->assertSame(1, FidyahPolicy::count());
    }

    public function test_a_published_policy_cannot_be_updated(): void
    {
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();
        $policy = $this->service()->publishNewVersion([
            'rate_amount_minor' => 3500000, 'currency' => 'IDR', 'effective_from' => Carbon::today()->toDateString(), 'status' => 'ACTIVE',
        ]);

        try {
            $policy->forceFill(['rate_amount_minor' => 1])->save();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(3500000, $policy->fresh()->rate_amount_minor);
    }

    public function test_a_published_policy_cannot_be_deleted(): void
    {
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();
        $policy = $this->service()->publishNewVersion([
            'rate_amount_minor' => 3500000, 'currency' => 'IDR', 'effective_from' => Carbon::today()->toDateString(), 'status' => 'ACTIVE',
        ]);

        try {
            $policy->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(FidyahPolicy::find($policy->id));
    }

    public function test_a_successor_version_does_not_alter_the_historical_row(): void
    {
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();

        $first = $this->service()->publishNewVersion([
            'rate_amount_minor' => 3500000, 'currency' => 'IDR',
            'effective_from' => Carbon::today()->toDateString(), 'effective_until' => Carbon::today()->addDays(90)->toDateString(),
            'status' => 'ACTIVE',
        ]);

        $this->service()->publishNewVersion([
            'rate_amount_minor' => 4000000, 'currency' => 'IDR',
            'effective_from' => Carbon::today()->addDays(91)->toDateString(), 'status' => 'ACTIVE',
        ]);

        $this->assertSame(3500000, $first->fresh()->rate_amount_minor);
    }

    public function test_an_ordinary_status_flip_to_active_via_save_is_denied(): void
    {
        $policy = $this->service()->publishNewVersion([
            'rate_amount_minor' => 3500000, 'currency' => 'IDR', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
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
        $policy = $this->service()->publishNewVersion([
            'rate_amount_minor' => 3500000, 'currency' => 'IDR', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        try {
            FidyahPolicy::where('id', $policy->id)->update(['status' => 'ACTIVE']);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame('DRAFT', $policy->fresh()->status);
    }

    public function test_direct_active_create_bypassing_the_service_is_denied(): void
    {
        try {
            FidyahPolicy::create([
                'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000,
                'currency' => 'IDR', 'effective_from' => '2026-01-01', 'status' => 'ACTIVE',
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, FidyahPolicy::count());
    }

    public function test_direct_draft_create_bypassing_the_service_is_allowed(): void
    {
        $policy = FidyahPolicy::create([
            'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000,
            'currency' => 'IDR', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $this->assertSame('DRAFT', $policy->status);
    }

    public function test_an_ordinary_edit_of_a_draft_row_that_does_not_touch_status_is_allowed(): void
    {
        $policy = $this->service()->publishNewVersion([
            'rate_amount_minor' => 3500000, 'currency' => 'IDR', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $policy->forceFill(['rate_amount_minor' => 4000000])->save();

        $this->assertSame(4000000, $policy->fresh()->rate_amount_minor);
    }

    public function test_a_historical_policy_builder_update_is_denied(): void
    {
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();
        $policy = $this->service()->publishNewVersion([
            'rate_amount_minor' => 3500000, 'currency' => 'IDR', 'effective_from' => Carbon::today()->toDateString(), 'status' => 'ACTIVE',
        ]);
        $originalSourceRef = $policy->source_ref;

        try {
            FidyahPolicy::where('id', $policy->id)->update(['source_ref' => 'irrelevant']);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame($originalSourceRef, $policy->fresh()->source_ref);
    }

    public function test_a_historical_policy_builder_delete_is_denied(): void
    {
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();
        $policy = $this->service()->publishNewVersion([
            'rate_amount_minor' => 3500000, 'currency' => 'IDR', 'effective_from' => Carbon::today()->toDateString(), 'status' => 'ACTIVE',
        ]);

        try {
            FidyahPolicy::where('id', $policy->id)->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(FidyahPolicy::find($policy->id));
    }

    public function test_an_invalid_effective_range_via_direct_persistence_is_denied(): void
    {
        try {
            FidyahPolicy::create([
                'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
                'effective_from' => '2026-06-01', 'effective_until' => '2026-01-01', 'status' => 'DRAFT',
            ]);
            $this->fail('Expected an InvalidArgumentException to be thrown.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(0, FidyahPolicy::count());
    }

    public function test_ambiguous_resolver_data_fails_closed(): void
    {
        DB::table('fidyah_policies')->insert([
            [
                'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
                'effective_from' => '2026-01-01', 'effective_until' => null, 'status' => 'ACTIVE',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'ulid' => (string) Str::ulid(), 'version' => 2, 'rate_amount_minor' => 4000000, 'currency' => 'IDR',
                'effective_from' => '2026-01-02', 'effective_until' => null, 'status' => 'ACTIVE',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        try {
            app(FidyahCalculatorService::class)->resolveApplicablePolicy(new \DateTimeImmutable('2026-06-01'));
            $this->fail('Expected a RuntimeException to be thrown.');
        } catch (\RuntimeException) {
        }

        $this->assertSame(2, FidyahPolicy::where('status', 'ACTIVE')->count());
    }

    // ------------------------------------------------------------------
    // CODEX-CR001B-02 Round 3 — forwarded low-level insert bypass and the
    // toBase()/getQuery() escape hatch, mirroring
    // ZakatPolicyVersioningServiceTest exactly.
    // ------------------------------------------------------------------

    public function test_forwarded_insert_with_active_status_is_denied_and_creates_nothing(): void
    {
        try {
            FidyahPolicy::query()->insert([
                'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
                'effective_from' => '2026-01-01', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, FidyahPolicy::count());
    }

    public function test_forwarded_insert_get_id_with_active_status_is_denied_and_creates_nothing(): void
    {
        try {
            FidyahPolicy::query()->insertGetId([
                'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
                'effective_from' => '2026-01-01', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, FidyahPolicy::count());
    }

    public function test_forwarded_insert_or_ignore_with_active_status_is_denied_and_creates_nothing(): void
    {
        try {
            FidyahPolicy::query()->insertOrIgnore([
                'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
                'effective_from' => '2026-01-01', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, FidyahPolicy::count());
    }

    public function test_forwarded_insert_with_draft_status_still_works(): void
    {
        FidyahPolicy::query()->insertGetId([
            'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
            'effective_from' => '2026-01-01', 'status' => 'DRAFT', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(1, FidyahPolicy::where('status', 'DRAFT')->count());
    }

    /**
     * CODEX-CR001B-02 Round 4 (R4-02): mirrors
     * ZakatPolicyVersioningServiceTest's insertOrIgnoreReturning
     * coverage exactly — see that test for the SQLite-only rationale.
     */
    public function test_insert_or_ignore_returning_with_draft_status_returns_a_collection_and_persists_once(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('insertOrIgnoreReturning is only exercised on SQLite here; MySQL\'s grammar does not implement RETURNING at all.');
        }

        $result = FidyahPolicy::query()->insertOrIgnoreReturning([
            'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
            'effective_from' => '2026-01-01', 'status' => 'DRAFT', 'created_at' => now(), 'updated_at' => now(),
        ], ['*'], 'ulid');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame(1, FidyahPolicy::where('status', 'DRAFT')->count());
    }

    public function test_insert_or_ignore_returning_with_active_status_is_denied_before_any_query_runs(): void
    {
        try {
            FidyahPolicy::query()->insertOrIgnoreReturning([
                'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
                'effective_from' => '2026-01-01', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
            ], ['*'], 'ulid');
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, FidyahPolicy::count());
    }

    public function test_to_base_truncate_is_denied_and_history_remains(): void
    {
        FidyahPolicy::create([
            'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
            'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        try {
            FidyahPolicy::query()->toBase()->truncate();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame(1, FidyahPolicy::count());
    }

    public function test_to_base_active_transition_is_denied_and_draft_remains(): void
    {
        $policy = $this->service()->publishNewVersion([
            'rate_amount_minor' => 3500000, 'currency' => 'IDR', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        try {
            FidyahPolicy::query()->where('id', $policy->id)->toBase()->update(['status' => 'ACTIVE']);
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertSame('DRAFT', $policy->fresh()->status);
    }

    public function test_get_query_historical_delete_is_denied_and_row_exists(): void
    {
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();
        $policy = $this->service()->publishNewVersion([
            'rate_amount_minor' => 3500000, 'currency' => 'IDR', 'effective_from' => Carbon::today()->toDateString(), 'status' => 'ACTIVE',
        ]);

        try {
            FidyahPolicy::query()->where('id', $policy->id)->getQuery()->delete();
            $this->fail('Expected a LogicException to be thrown.');
        } catch (\LogicException) {
        }

        $this->assertNotNull(FidyahPolicy::find($policy->id));
    }

    public function test_ordinary_reads_still_work_after_the_base_query_builder_change(): void
    {
        $policy = FidyahPolicy::create([
            'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
            'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $this->assertTrue(FidyahPolicy::query()->where('id', $policy->id)->exists());
        $this->assertSame(1, FidyahPolicy::query()->count());
        $this->assertNotNull(FidyahPolicy::find($policy->id));
    }
}
