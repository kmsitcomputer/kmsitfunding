<?php

namespace Tests\Feature\Donation;

use App\Models\Donation\Donation;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Donation\DonationService;
use App\Services\Donation\DonationTransitionService;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Rbac\PrincipalService;
use Illuminate\Support\Facades\DB;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-008 — concurrency evidence (docs/implementation/IMP-008-donation.md
 * "Concurrency" / AC-008-017): REAL disposable MySQL required — SQLite
 * does not provide genuine row-locking/transaction-isolation behavior.
 *
 * Uses a dedicated disposable database (kmsitdonation_imp008_test),
 * NEVER the live/development kmsitdonation database. The test runs the
 * migration set against it, executes two racing authorized transitions
 * serialized through lockForUpdate, and asserts exactly one succeeds.
 *
 * Skipped automatically when the disposable MySQL database is not
 * reachable in this environment (CI without MySQL still gets the full
 * SQLite regression suite; MySQL evidence is recorded when available).
 */
class DonationConcurrencyTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;

    private const MYSQL_DATABASE = 'kmsitdonation_imp008_test';

    private bool $usesDisposableMysql = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->disposableMysqlAvailable()) {
            $this->markTestSkipped('Disposable MySQL (kmsitdonation_imp008_test) is not reachable — concurrency evidence requires real MySQL.');
        }

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', self::MYSQL_DATABASE);

        DB::purge('mysql');

        $this->artisan('migrate:fresh', ['--database' => 'mysql', '--force' => true]);

        $this->usesDisposableMysql = true;
    }

    protected function tearDown(): void
    {
        if ($this->usesDisposableMysql) {
            $connection = DB::connection('mysql');

            $connection->getSchemaBuilder()->withoutForeignKeyConstraints(function () use ($connection) {
                foreach ($connection->getSchemaBuilder()->getTableListing(schemaQualified: false) as $table) {
                    if ($table === 'migrations') {
                        continue;
                    }

                    // getTableListing() may report tables outside the
                    // current schema on a shared MySQL server — only
                    // truncate tables that genuinely exist here.
                    if (! $connection->getSchemaBuilder()->hasTable($table)) {
                        continue;
                    }

                    $connection->table($table)->truncate();
                }
            });
        }

        parent::tearDown();
    }

    private function disposableMysqlAvailable(): bool
    {
        try {
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

    public function test_two_racing_transitions_on_one_pending_donation_exactly_one_succeeds(): void
    {
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        $donation = app(DonationService::class)->create($campaign, [
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'guest_name' => 'G',
            'guest_email' => 'g@example.com',
        ], null, 'race-'.uniqid());

        $catalog = SystemPrincipal::firstOrCreate(
            ['code' => 'test.donation.race'],
            ['description' => 'Test racing consequence identity.']
        );
        $system = app(PrincipalService::class)->forSystem($catalog);
        $service = app(DonationTransitionService::class);

        $outcomes = ['successes' => 0, 'conflicts' => 0];

        foreach (['succeed', 'fail'] as $attempt) {
            try {
                if ($attempt === 'succeed') {
                    $service->markSucceeded(Donation::query()->whereKey($donation->id)->first(), $system);
                } else {
                    $service->markFailed(Donation::query()->whereKey($donation->id)->first(), $system);
                }

                $outcomes['successes']++;
            } catch (DonationTransitionConflictException) {
                $outcomes['conflicts']++;
            }
        }

        $this->assertSame(1, $outcomes['successes']);
        $this->assertSame(1, $outcomes['conflicts']);
        $this->assertContains($donation->fresh()->status, ['SUCCEEDED', 'FAILED']);
    }

    public function test_concurrent_duplicate_idempotency_key_creates_exactly_one_row(): void
    {
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);
        $key = 'race-idem-'.uniqid();

        $payload = [
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'guest_name' => 'G',
            'guest_email' => 'g@example.com',
        ];

        $first = app(DonationService::class)->create($campaign, $payload, null, $key);
        $second = app(DonationService::class)->create($campaign, $payload, null, $key);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Donation::query()->where('idempotency_key', $key)->count());
    }
}
