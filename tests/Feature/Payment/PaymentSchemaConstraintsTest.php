<?php

namespace Tests\Feature\Payment;

use App\Services\Donation\DonationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-009 — MySQL schema constraints + migration UP/DOWN/UP
 * (docs/implementation/IMP-009-payment-hub.md "Database Impact" /
 * AC-009-015/021): RESTRICT on donation/payment deletion attempts
 * (raw query, bypassing the service layer); (provider,
 * provider_reference) and (provider, provider_event_id) uniqueness
 * proven at the DB level; every IMP-009 migration runs UP, DOWN, UP
 * cleanly against the disposable MySQL schema with explicit
 * dependency-order reversal (no FOREIGN_KEY_CHECKS=0 default).
 *
 * Disposable database only (kmsitdonation_imp009_test) — never the
 * development database. Skipped when unreachable.
 */
class PaymentSchemaConstraintsTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;

    private const MYSQL_DATABASE = 'kmsitdonation_imp009_test';

    private const DEV_DATABASE = 'kmsitdonation';

    private bool $usesDisposableMysql = false;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.mysql.database', self::MYSQL_DATABASE);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('Disposable MySQL (kmsitdonation_imp009_test) is not reachable.');
        }

        $this->assertNotSame(self::DEV_DATABASE, config('database.connections.mysql.database'));

        config()->set('database.default', 'mysql');

        $this->artisan('migrate:fresh', ['--database' => 'mysql', '--force' => true]);

        $this->usesDisposableMysql = true;
    }

    protected function tearDown(): void
    {
        if ($this->usesDisposableMysql) {
            $connection = DB::connection('mysql');

            $connection->getSchemaBuilder()->withoutForeignKeyConstraints(function () use ($connection) {
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

    private function seedDonation(): int
    {
        // Built through the real service path — never hand-replicated
        // campaign/donation columns — so raw payment/event inserts
        // below exercise genuine FK targets.
        $seeder = $this->makeAuthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        return app(DonationService::class)->create($campaign, [
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'guest_name' => 'G',
            'guest_email' => 'schema-'.uniqid().'@example.com',
        ], null, 'schema-don-'.uniqid())->id;
    }

    private function seedPayment(int $donationId, string $key, string $status = 'PENDING'): int
    {
        return DB::table('payments')->insertGetId([
            'ulid' => (string) Str::ulid(),
            'donation_id' => $donationId,
            'provider' => 'manual_transfer',
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'status' => $status,
            'idempotency_key' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_donation_delete_is_restricted_while_payments_reference_it(): void
    {
        $donationId = $this->seedDonation();
        $this->seedPayment($donationId, 'schema-restr-'.uniqid());

        try {
            DB::table('donations')->where('id', $donationId)->delete();
            $this->fail('Deleting a Donation referenced by a Payment must be rejected at the DB level (RESTRICT).');
        } catch (QueryException $e) {
            $this->assertSame(1451, $e->errorInfo[1] ?? null);
        }

        $this->assertSame(1, DB::table('donations')->where('id', $donationId)->count());
    }

    public function test_payment_delete_is_restricted_while_events_reference_it(): void
    {
        $donationId = $this->seedDonation();
        $paymentId = $this->seedPayment($donationId, 'schema-evr-'.uniqid());

        DB::table('payment_provider_events')->insert([
            'ulid' => (string) Str::ulid(),
            'payment_id' => $paymentId,
            'provider' => 'tripay',
            'event_type' => 'webhook',
            'signature_valid' => true,
            'processing_result' => 'ACCEPTED',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::table('payments')->where('id', $paymentId)->delete();
            $this->fail('Deleting a Payment referenced by a provider event must be rejected (RESTRICT).');
        } catch (QueryException $e) {
            $this->assertSame(1451, $e->errorInfo[1] ?? null);
        }
    }

    public function test_provider_reference_and_event_id_uniqueness_hold_at_the_db_level(): void
    {
        $donationId = $this->seedDonation();
        $paymentId = $this->seedPayment($donationId, 'schema-u1-'.uniqid());

        DB::table('payments')->where('id', $paymentId)->update(['provider_reference' => 'REF-DUP']);

        $otherId = $this->seedPayment($donationId, 'schema-u2-'.uniqid(), 'SUCCEEDED');

        try {
            DB::table('payments')->where('id', $otherId)->update(['provider_reference' => 'REF-DUP']);
            $this->fail('(provider, provider_reference) must be unique.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1] ?? null);
        }

        DB::table('payment_provider_events')->insert([
            'ulid' => (string) Str::ulid(),
            'payment_id' => $paymentId,
            'provider' => 'stripe',
            'provider_event_id' => 'evt-dup',
            'event_type' => 'webhook',
            'signature_valid' => true,
            'processing_result' => 'ACCEPTED',
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::table('payment_provider_events')->insert([
                'ulid' => (string) Str::ulid(),
                'payment_id' => $paymentId,
                'provider' => 'stripe',
                'provider_event_id' => 'evt-dup',
                'event_type' => 'webhook',
                'signature_valid' => true,
                'processing_result' => 'ACCEPTED',
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('(provider, provider_event_id) must be unique.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1] ?? null);
        }
    }

    public function test_idempotency_key_uniqueness_holds_at_the_db_level(): void
    {
        $donationId = $this->seedDonation();
        $key = 'schema-idem-'.uniqid();
        $this->seedPayment($donationId, $key);

        try {
            $this->seedPayment($donationId, $key);
            $this->fail('payments.idempotency_key must be unique.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1] ?? null);
        }
    }

    /**
     * Every IMP-009 migration runs UP, DOWN, UP cleanly with
     * explicit dependency-order reversal (evidence -> events ->
     * payments -> credentials -> bank_accounts): no
     * FOREIGN_KEY_CHECKS=0 default, no hidden ordering.
     *
     * @group mysql-migration
     */
    public function test_imp009_migrations_run_up_down_up_in_dependency_order(): void
    {
        $migrations = [
            '0001_09_01_000005_create_manual_transfer_evidence_table',
            '0001_09_01_000004_create_payment_provider_events_table',
            '0001_09_01_000003_create_payments_table',
            '0001_09_01_000002_create_payment_provider_credentials_table',
            '0001_09_01_000001_create_manual_transfer_bank_accounts_table',
        ];

        foreach ($migrations as $migration) {
            $this->artisan('migrate:rollback', [
                '--database' => 'mysql',
                '--force' => true,
                '--path' => "database/migrations/{$migration}.php",
            ])->assertSuccessful();
        }

        foreach ([
            'manual_transfer_evidence',
            'payment_provider_events',
            'payments',
            'payment_provider_credentials',
            'manual_transfer_bank_accounts',
        ] as $table) {
            $this->assertFalse(Schema::connection('mysql')->hasTable($table), "{$table} must be gone after DOWN.");
        }

        $this->artisan('migrate', ['--database' => 'mysql', '--force' => true])->assertSuccessful();

        foreach ([
            'manual_transfer_evidence',
            'payment_provider_events',
            'payments',
            'payment_provider_credentials',
            'manual_transfer_bank_accounts',
        ] as $table) {
            $this->assertTrue(Schema::connection('mysql')->hasTable($table), "{$table} must exist after UP.");
        }

        $columns = Schema::connection('mysql')->getColumnListing('payments');
        $this->assertContains('active_slot', $columns);

        $indexes = collect(DB::connection('mysql')->select('SHOW INDEX FROM payments'))
            ->pluck('Key_name')
            ->unique()
            ->all();
        $this->assertContains('ux_payments_donation_active_slot', $indexes);
        $this->assertContains('ux_payments_provider_reference', $indexes);
    }
}
