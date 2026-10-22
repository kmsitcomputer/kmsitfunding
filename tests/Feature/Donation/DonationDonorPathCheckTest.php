<?php

namespace Tests\Feature\Donation;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * IMP-008 Codex final audit FINAL-03 — BR-2 donor-path XOR at the MySQL
 * CHECK level (migration `0001_08_01_000002`, constraint
 * `chk_donations_donor_path`).
 *
 * RAW MySQL proof bypassing Eloquent (no model events involved): raw
 * INSERTs against a disposable database (`kmsitdonation_imp008_xor`),
 * NEVER the development `kmsitdonation` database. The migration set runs
 * against the disposable database, so the CHECK under test is the real
 * migrated constraint.
 *
 * - VALID authenticated-only row: accepted.
 * - VALID guest-only row: accepted.
 * - INVALID neither: rejected by the DB CHECK.
 * - INVALID both: rejected by the DB CHECK (the critical new proof).
 *
 * Also proves migration reversibility (UP -> DOWN -> UP) on the same
 * disposable database.
 *
 * Skipped automatically when the disposable MySQL database is not
 * reachable (SQLite cannot carry this MySQL CHECK by design — the
 * application-level `Donation::isDonorPathConsistent()` guard is the
 * SQLite enforcement layer and is covered by
 * `DonationSchemaConstraintsTest`).
 */
class DonationDonorPathCheckTest extends TestCase
{
    private const MYSQL_DATABASE = 'kmsitdonation_imp008_xor';

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->disposableMysqlAvailable()) {
            $this->markTestSkipped('Disposable MySQL ('.self::MYSQL_DATABASE.') is not reachable — DB CHECK evidence requires real MySQL.');
        }

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', self::MYSQL_DATABASE);

        DB::purge('mysql');

        $this->artisan('migrate:fresh', ['--database' => 'mysql', '--force' => true]);
    }

    protected function tearDown(): void
    {
        try {
            while (DB::connection('mysql')->transactionLevel() > 0) {
                DB::connection('mysql')->rollBack();
            }
        } catch (\Throwable) {
        }

        DB::purge('mysql');

        parent::tearDown();
    }

    private function disposableMysqlAvailable(): bool
    {
        try {
            // Point the mysql connection at the disposable database BEFORE
            // connecting: the surrounding test process may carry
            // DB_DATABASE=:memory: (SQLite regression), which is not a
            // valid MySQL dbname and would fail the PDO handshake.
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

    private function seedIds(): array
    {
        $userId = DB::connection('mysql')->table('users')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'email' => 'xor-'.uniqid().'@example.com',
            'password' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $principalId = DB::connection('mysql')->table('principals')->insertGetId([
            'principal_kind' => 'human',
            'human_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $campaignId = DB::connection('mysql')->table('campaigns')->insertGetId([
            'ulid' => (string) Str::ulid(),
            'name' => 'XOR Campaign',
            'slug' => 'xor-campaign-'.uniqid(),
            'status' => 'PUBLISHED',
            'edit_version' => 0,
            'created_by_principal_id' => $principalId,
            'updated_by_principal_id' => $principalId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$campaignId, $principalId];
    }

    private function rawInsert(int $campaignId, array $overrides): void
    {
        DB::connection('mysql')->table('donations')->insert(array_merge([
            'ulid' => (string) Str::ulid(),
            'campaign_id' => $campaignId,
            'donor_principal_id' => null,
            'guest_name' => null,
            'guest_email' => null,
            'amount_minor' => 100,
            'currency' => 'IDR',
            'status' => 'PENDING',
            'is_anonymous' => false,
            'idempotency_key' => 'xor-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function assertCheckViolation(\Throwable $e): void
    {
        $this->assertInstanceOf(QueryException::class, $e);
        $this->assertSame(3819, $e->errorInfo[1] ?? null, 'Expected MySQL error 3819 (check constraint violated). Got: '.$e->getMessage());
    }

    public function test_raw_mysql_accepts_an_authenticated_only_row(): void
    {
        [$campaignId, $principalId] = $this->seedIds();

        $this->rawInsert($campaignId, [
            'donor_principal_id' => $principalId,
            'guest_name' => null,
            'guest_email' => null,
        ]);

        $this->assertSame(1, DB::connection('mysql')->table('donations')->count());
    }

    public function test_raw_mysql_accepts_a_guest_only_row(): void
    {
        [$campaignId] = $this->seedIds();

        $this->rawInsert($campaignId, [
            'donor_principal_id' => null,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.com',
        ]);

        $this->assertSame(1, DB::connection('mysql')->table('donations')->count());
    }

    public function test_raw_mysql_rejects_neither_path_populated(): void
    {
        [$campaignId] = $this->seedIds();

        try {
            $this->rawInsert($campaignId, [
                'donor_principal_id' => null,
                'guest_name' => null,
                'guest_email' => null,
            ]);

            $this->fail('A row with neither donor path populated must be rejected by chk_donations_donor_path.');
        } catch (\Throwable $e) {
            $this->assertCheckViolation($e);
        }

        $this->assertSame(0, DB::connection('mysql')->table('donations')->count());
    }

    public function test_raw_mysql_rejects_both_paths_populated(): void
    {
        [$campaignId, $principalId] = $this->seedIds();

        try {
            $this->rawInsert($campaignId, [
                'donor_principal_id' => $principalId,
                'guest_name' => 'Guest',
                'guest_email' => 'guest@example.com',
            ]);

            $this->fail('A row with BOTH donor paths populated must be rejected by chk_donations_donor_path.');
        } catch (\Throwable $e) {
            $this->assertCheckViolation($e);
        }

        $this->assertSame(0, DB::connection('mysql')->table('donations')->count());
    }

    public function test_migration_reversibility_up_down_up(): void
    {
        $this->artisan('migrate:rollback', ['--database' => 'mysql', '--force' => true]);

        $this->assertFalse(
            DB::connection('mysql')->getSchemaBuilder()->hasTable('donations'),
            'After DOWN, the donations table must be gone.'
        );

        $this->artisan('migrate', ['--database' => 'mysql', '--force' => true]);

        $this->assertTrue(
            DB::connection('mysql')->getSchemaBuilder()->hasTable('donations'),
            'After re-UP, the donations table must exist again.'
        );

        [$campaignId, $principalId] = $this->seedIds();

        try {
            $this->rawInsert($campaignId, [
                'donor_principal_id' => $principalId,
                'guest_name' => 'Guest',
                'guest_email' => 'guest@example.com',
            ]);

            $this->fail('After re-UP, BOTH-populated rows must still be rejected by the re-created CHECK.');
        } catch (\Throwable $e) {
            $this->assertCheckViolation($e);
        }
    }
}
