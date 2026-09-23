<?php

namespace Tests\Feature\Zakat;

use App\Models\Fidyah\FidyahPolicy;
use App\Models\PolicyLeadTimeConfig;
use App\Models\Zakat\ZakatPolicy;
use App\Models\Zakat\ZakatType;
use App\Services\Zakat\ZakatPolicyVersioningService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CODEX-CR001B-01 / CODEX-CR001B-02 remediation — GENUINE real-MySQL
 * evidence, mirroring the connection-swap technique established by
 * tests/Feature/Payment/PaymentConcurrencyTest.php exactly.
 *
 * IMPORTANT CORRECTION: `tests/bootstrap.php` force-sets DB_CONNECTION to
 * sqlite for the entire PHPUnit process before Laravel even boots
 * (documented there as IMP-009 remediation §28 hardening). Passing
 * `--env=testing` to `php artisan test` does NOT override this — that
 * flag is processed by Laravel's console kernel, which boots strictly
 * after tests/bootstrap.php has already forced sqlite via $_SERVER/$_ENV/
 * putenv, which phpdotenv's default (non-overload) loader will not
 * un-force. The original CR-001-B implementation round's claimed "real
 * MySQL verification via --env=testing" therefore actually ran on SQLite.
 * This class is the corrected version: it explicitly repoints Laravel's
 * OWN `mysql` connection config at the disposable database at runtime
 * (config()->set + DB::purge), the only mechanism in this codebase that
 * genuinely reaches MySQL from within PHPUnit — see
 * PaymentConcurrencyTest for the established precedent.
 */
class CR001BMysqlEvidenceTest extends TestCase
{
    private const MYSQL_DATABASE = 'kmsitdonation_imp003_test';

    private const DEV_DATABASE = 'kmsitdonation';

    private bool $usesDisposableMysql = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->disposableMysqlAvailable()) {
            $this->markTestSkipped('Disposable MySQL (kmsitdonation_imp003_test) is not reachable.');
        }

        $this->assertNotSame(
            self::DEV_DATABASE,
            config('database.connections.mysql.database'),
            'Refusing to run destructive verification against the development database.'
        );

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', self::MYSQL_DATABASE);
        DB::purge('mysql');

        $this->artisan('migrate:fresh', ['--database' => 'mysql', '--force' => true]);

        $this->usesDisposableMysql = true;
    }

    protected function tearDown(): void
    {
        if ($this->usesDisposableMysql) {
            try {
                while (DB::connection('mysql')->transactionLevel() > 0) {
                    DB::connection('mysql')->rollBack();
                }
            } catch (\Throwable) {
            }

            $connection = DB::connection('mysql');
            $connection->getSchemaBuilder()->withoutForeignKeyConstraints(function () use ($connection): void {
                foreach ($connection->getSchemaBuilder()->getTableListing(schemaQualified: false) as $table) {
                    if ($table === 'migrations' || ! $connection->getSchemaBuilder()->hasTable($table)) {
                        continue;
                    }
                    $connection->table($table)->truncate();
                }
            });

            DB::purge('mysql');
        }

        parent::tearDown();
    }

    private function disposableMysqlAvailable(): bool
    {
        try {
            config()->set('database.connections.mysql.database', self::MYSQL_DATABASE);
            DB::purge('mysql');
            DB::connection('mysql')->getPdo();

            $databases = DB::connection('mysql')->select('SHOW DATABASES');

            foreach ($databases as $row) {
                if (in_array(self::MYSQL_DATABASE, array_values((array) $row), true)) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function test_the_migration_baseline_is_confirmed_on_the_real_mysql_driver(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
    }

    public function test_policy_lead_time_config_singleton_check_constraint_is_enforced(): void
    {
        $this->expectException(QueryException::class);

        DB::table('policy_lead_time_configs')->insert(['id' => 2, 'lead_time_days' => 7, 'updated_at' => now()]);
    }

    public function test_zakat_calculation_snapshot_update_trigger_is_enforced(): void
    {
        if (! $this->triggerExists('trg_zakat_calc_snapshots_no_update')) {
            $this->markTestSkipped(
                'INDETERMINATE: trg_zakat_calc_snapshots_no_update was not installed on this MySQL host '.
                '(insufficient privilege — see migration docblock). Application-layer immutability is '.
                'covered separately and remains enforced regardless.'
            );
        }

        $snapshotId = $this->makeZakatSnapshotRow();

        $this->expectException(QueryException::class);
        DB::statement('UPDATE zakat_calculation_snapshots SET computed_at = ? WHERE id = ?', [now(), $snapshotId]);
    }

    public function test_zakat_calculation_snapshot_delete_trigger_is_enforced(): void
    {
        if (! $this->triggerExists('trg_zakat_calc_snapshots_no_delete')) {
            $this->markTestSkipped('INDETERMINATE: trg_zakat_calc_snapshots_no_delete was not installed on this MySQL host (insufficient privilege).');
        }

        $snapshotId = $this->makeZakatSnapshotRow();

        $this->expectException(QueryException::class);
        DB::statement('DELETE FROM zakat_calculation_snapshots WHERE id = ?', [$snapshotId]);
    }

    public function test_fidyah_calculation_snapshot_update_trigger_is_enforced(): void
    {
        if (! $this->triggerExists('trg_fidyah_calc_snapshots_no_update')) {
            $this->markTestSkipped('INDETERMINATE: trg_fidyah_calc_snapshots_no_update was not installed on this MySQL host (insufficient privilege).');
        }

        $policy = FidyahPolicy::create([
            'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
            'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);
        $snapshotId = DB::table('fidyah_calculation_snapshots')->insertGetId([
            'ulid' => (string) Str::ulid(), 'fidyah_policy_id' => $policy->id,
            'input' => '{}', 'result' => '{}', 'computed_at' => now(), 'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::statement('UPDATE fidyah_calculation_snapshots SET computed_at = ? WHERE id = ?', [now(), $snapshotId]);
    }

    private function triggerExists(string $name): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM information_schema.triggers WHERE trigger_schema = ? AND trigger_name = ?',
            [self::MYSQL_DATABASE, $name],
        );

        return ((int) $row->cnt) > 0;
    }

    public function test_zakat_policies_same_effective_date_unique_constraint_is_enforced(): void
    {
        $type = ZakatType::create(['ulid' => (string) Str::ulid(), 'code' => 'MAAL', 'name' => 'Zakat Maal']);
        ZakatPolicy::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $this->expectException(QueryException::class);
        ZakatPolicy::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 2,
            'rate' => '0.030000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);
    }

    public function test_fidyah_policies_same_effective_date_unique_constraint_is_enforced(): void
    {
        FidyahPolicy::create([
            'ulid' => (string) Str::ulid(), 'version' => 1, 'rate_amount_minor' => 3500000, 'currency' => 'IDR',
            'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        $this->expectException(QueryException::class);
        FidyahPolicy::create([
            'ulid' => (string) Str::ulid(), 'version' => 2, 'rate_amount_minor' => 4000000, 'currency' => 'IDR',
            'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);
    }

    public function test_a_genuinely_concurrent_lock_holder_causes_the_service_to_fail_closed(): void
    {
        $type = ZakatType::create(['ulid' => (string) Str::ulid(), 'code' => 'MAAL', 'name' => 'Zakat Maal']);
        PolicyLeadTimeConfig::current()->forceFill(['lead_time_days' => 0])->save();

        $lockKey = "zakat_policy_type_{$type->id}";

        // A genuinely separate physical connection holds the advisory
        // lock — proves real cross-connection contention, not a
        // same-connection re-acquire.
        $mysqlConfig = config('database.connections.mysql');
        $contender = new \PDO(
            "mysql:host={$mysqlConfig['host']};port={$mysqlConfig['port']};dbname={$mysqlConfig['database']}",
            $mysqlConfig['username'],
            $mysqlConfig['password'],
        );
        $stmt = $contender->prepare('SELECT GET_LOCK(?, 1)');
        $stmt->execute([$lockKey]);
        $this->assertSame(1, (int) $stmt->fetchColumn());

        try {
            $this->expectException(\RuntimeException::class);
            app(ZakatPolicyVersioningService::class)->publishNewVersion($type, [
                'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G',
                'effective_from' => Carbon::today()->toDateString(), 'status' => 'ACTIVE',
            ]);
        } finally {
            $release = $contender->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockKey]);
        }
    }

    public function test_insert_still_succeeds_on_snapshot_tables_after_trigger_installation(): void
    {
        $snapshotId = $this->makeZakatSnapshotRow();

        $this->assertNotNull($snapshotId);
        $this->assertSame(1, DB::table('zakat_calculation_snapshots')->count());
    }

    private function makeZakatSnapshotRow(): int
    {
        $type = ZakatType::create(['ulid' => (string) Str::ulid(), 'code' => 'MAAL', 'name' => 'Zakat Maal']);
        $policy = ZakatPolicy::create([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'version' => 1,
            'rate' => '0.025000', 'nisab_basis' => 'GOLD_85G', 'effective_from' => '2026-01-01', 'status' => 'DRAFT',
        ]);

        return DB::table('zakat_calculation_snapshots')->insertGetId([
            'ulid' => (string) Str::ulid(), 'zakat_type_id' => $type->id, 'zakat_policy_id' => $policy->id,
            'input' => '{}', 'result' => '{}', 'computed_at' => now(), 'created_at' => now(),
        ]);
    }
}
