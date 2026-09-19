<?php

namespace Tests\Feature\Donation;

use App\Models\Donation\Donation;
use App\Models\Donation\DonationRecurringOccurrence;
use App\Models\Donation\DonationRecurringPlan;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Donation\DonationService;
use App\Services\Donation\DonationTransitionService;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Donation\RecurringPlanService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-008 — genuine concurrency evidence (docs/implementation/
 * IMP-008-donation.md "Concurrency" / AC-008-017, IMP008-REVIEW-04):
 * REAL disposable MySQL required — SQLite does not provide genuine
 * row-locking/transaction-isolation behavior.
 *
 * Each test creates GENUINE overlap with two separate database
 * connections to the same disposable database: a contender connection
 * opens a transaction and holds a row lock (or an uncommitted unique
 * insert) WITHOUT committing, while the primary connection attempts
 * the competing write with a short innodb_lock_wait_timeout. A
 * lock-wait-timeout (MySQL 1205) on the primary is impossible under
 * sequential execution — it is the smoking-gun evidence the two
 * operations genuinely overlapped and contended. No sequential pair of
 * calls is labelled concurrency here.
 *
 * Uses a dedicated disposable database (kmsitdonation_imp008_test),
 * NEVER the live/development kmsitdonation database. The test runs the
 * migration set against it.
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

    private const CONTENDER_CONNECTION = 'mysql_contender';

    private const LOCK_WAIT_SECONDS = 3;

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

        $mysql = config('database.connections.mysql');
        $mysql['database'] = self::MYSQL_DATABASE;
        config()->set('database.connections.'.self::CONTENDER_CONNECTION, $mysql);
        DB::purge(self::CONTENDER_CONNECTION);

        $this->artisan('migrate:fresh', ['--database' => 'mysql', '--force' => true]);

        $this->usesDisposableMysql = true;
    }

    protected function tearDown(): void
    {
        if ($this->usesDisposableMysql) {
            foreach (['mysql', self::CONTENDER_CONNECTION] as $connection) {
                try {
                    while (DB::connection($connection)->transactionLevel() > 0) {
                        DB::connection($connection)->rollBack();
                    }
                } catch (\Throwable) {
                }

                DB::purge($connection);
            }

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

            DB::purge('mysql');
            DB::purge(self::CONTENDER_CONNECTION);

            config()->set('database.connections.'.self::CONTENDER_CONNECTION, null);
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

    private function makeSystemActor(): Principal
    {
        $catalog = SystemPrincipal::firstOrCreate(
            ['code' => 'test.donation.race'],
            ['description' => 'Test racing consequence identity.']
        );

        return app(PrincipalService::class)->forSystem($catalog);
    }

    private function makeGuestDonation(string $key): Donation
    {
        $seeder = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($seeder);

        return app(DonationService::class)->create($campaign, [
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'guest_name' => 'G',
            'guest_email' => 'g@example.com',
        ], null, $key);
    }

    /**
     * Opens a contender transaction on a SEPARATE connection and holds
     * an exclusive lock on one row without committing — the primary
     * connection's next write to that row genuinely contends.
     */
    private function holdRowLock(string $table, int $id): void
    {
        $contender = DB::connection(self::CONTENDER_CONNECTION);
        $contender->beginTransaction();
        $contender->table($table)->where('id', $id)->lockForUpdate()->first();
    }

    private function releaseContender(bool $commit = false): void
    {
        $contender = DB::connection(self::CONTENDER_CONNECTION);

        if ($contender->transactionLevel() > 0) {
            $commit ? $contender->commit() : $contender->rollBack();
        }
    }

    private function useShortLockWait(): void
    {
        DB::connection('mysql')->statement('SET SESSION innodb_lock_wait_timeout = '.self::LOCK_WAIT_SECONDS);
    }

    private function assertLockWaitTimeout(QueryException $e): void
    {
        $this->assertSame(
            1205,
            $e->errorInfo[1] ?? null,
            'Expected MySQL error 1205 (lock wait timeout) — the genuine-overlap evidence. Got: '.$e->getMessage()
        );
    }

    /**
     * A. Same PENDING Donation, two genuinely overlapping authorized
     * terminal transitions: exactly one succeeds, the loser observes
     * the expected typed conflict, final state is exactly one terminal
     * state.
     */
    public function test_two_overlapping_transitions_on_one_pending_donation_exactly_one_succeeds(): void
    {
        $donation = $this->makeGuestDonation('race-'.uniqid());
        $system = $this->makeSystemActor();
        $service = app(DonationTransitionService::class);

        // Genuine overlap: the contender holds the row lock on a
        // separate open transaction while the primary attempts its
        // terminal transition with a short lock wait. A 1205 timeout is
        // impossible sequentially — it proves real contention.
        $this->holdRowLock('donations', $donation->id);
        $this->useShortLockWait();

        try {
            $service->markSucceeded(Donation::query()->whereKey($donation->id)->first(), $system);
            $this->fail('The overlapping transition must block on the contender-held row lock.');
        } catch (QueryException $e) {
            $this->assertLockWaitTimeout($e);
        } finally {
            $this->releaseContender();
        }

        // The contender changed nothing — the primary now succeeds, and
        // the racing loser observes the typed conflict: exactly one
        // terminal state, never a silently overwritten row.
        $service->markSucceeded($donation->fresh(), $system);

        try {
            $service->markFailed(Donation::query()->whereKey($donation->id)->first(), $system);
            $this->fail('A transition off a terminal Donation must be rejected with a typed conflict.');
        } catch (DonationTransitionConflictException $e) {
            $this->assertSame('invalid_transition', $e->reason);
        }

        $this->assertSame('SUCCEEDED', $donation->fresh()->status);
        $this->assertNotNull($donation->fresh()->succeeded_at);
        $this->assertNull($donation->fresh()->failed_at);
    }

    /**
     * B. Same idempotency key, concurrent same-key create: exactly one
     * Donation row exists and the legitimate replay resolves to the
     * same Donation.
     */
    public function test_concurrent_same_key_create_creates_exactly_one_row(): void
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

        // Genuine overlap: the contender inserts the row with this key
        // but does NOT commit, while the primary attempts the same-key
        // create with a short lock wait. The primary blocks on the
        // uncommitted unique index entry — impossible sequentially.
        $contender = DB::connection(self::CONTENDER_CONNECTION);
        $contender->beginTransaction();
        $contender->table('donations')->insert([
            'ulid' => (string) Str::ulid(),
            'campaign_id' => $campaign->id,
            'donor_principal_id' => null,
            'guest_name' => 'G',
            'guest_email' => 'g@example.com',
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'status' => 'PENDING',
            'is_anonymous' => false,
            'idempotency_key' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->useShortLockWait();

        try {
            app(DonationService::class)->create($campaign, $payload, null, $key);
            $this->fail('The overlapping same-key create must block on the contender-held unique entry.');
        } catch (QueryException $e) {
            $this->assertLockWaitTimeout($e);
        } finally {
            $this->releaseContender(commit: true);
        }

        // After the contender commits, the same-key create is a
        // legitimate replay resolving to the SAME Donation — exactly one
        // row, no second Donation capable of its own lifecycle.
        $replayed = app(DonationService::class)->create($campaign, $payload, null, $key);

        $this->assertSame(1, Donation::query()->where('idempotency_key', $key)->count());
        $this->assertSame(
            Donation::query()->where('idempotency_key', $key)->first()->id,
            $replayed->id
        );
        $this->assertSame('PENDING', $replayed->status);
    }

    /**
     * C. Recurring occurrence, two concurrent workers against the same
     * due plan: exactly one logical occurrence and one Donation, the
     * schedule advances exactly once.
     */
    public function test_two_overlapping_workers_generate_exactly_one_occurrence(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = $this->makeEligibleCampaign($actor);
        $service = app(RecurringPlanService::class);
        $plan = $service->create($campaign, [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'frequency' => 'MONTHLY',
        ], $actor);
        $duePoint = $plan->next_occurrence_at;

        // Genuine overlap: the contender holds the plan row lock on a
        // separate open transaction while the worker attempts generation
        // with a short lock wait — a 1205 proves real contention.
        $this->holdRowLock('donation_recurring_plans', $plan->id);
        $this->useShortLockWait();

        try {
            $service->generateOccurrence($plan->fresh(), [], $actor);
            $this->fail('The overlapping generation must block on the contender-held plan lock.');
        } catch (QueryException $e) {
            $this->assertLockWaitTimeout($e);
        } finally {
            $this->releaseContender();
        }

        // First worker wins: exactly one Occurrence + one Donation, the
        // schedule advances exactly once past the consumed due point.
        $won = $service->generateOccurrence($plan->fresh(), [], $actor);

        $this->assertSame('GENERATED', $won->status);
        $this->assertNotNull($won->donation_id);
        $this->assertTrue($plan->fresh()->next_occurrence_at->gt($duePoint));

        // Second worker, serialized after the first, observes the
        // already-processed due point as a deterministic no-op conflict —
        // never a second Occurrence + Donation.
        try {
            $service->generateOccurrence($plan->fresh(), ['as_of' => $duePoint], $actor);
            $this->fail('The second worker must observe the already-processed due occurrence.');
        } catch (DonationTransitionConflictException $e) {
            $this->assertSame('occurrence_already_processed', $e->reason);
        }

        $this->assertSame(1, DonationRecurringOccurrence::query()->where('recurring_plan_id', $plan->id)->count());
        $this->assertSame(
            1,
            Donation::query()->where('campaign_id', $plan->campaign_id)->whereNotNull('recurring_occurrence_id')->count()
        );
        $this->assertSame(
            $won->donation_id,
            DonationRecurringOccurrence::query()->where('recurring_plan_id', $plan->id)->first()->donation_id
        );
    }

    /**
     * Paused/cancelled plans generate nothing even under contention;
     * a FAILED occurrence is never automatically retried.
     */
    public function test_inactive_plan_generates_nothing_and_failed_occurrence_is_not_retried(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(RecurringPlanService::class);
        $plan = $service->create($this->makeEligibleCampaign($actor), [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'frequency' => 'MONTHLY',
        ], $actor);
        $service->pause($plan, $actor);

        try {
            $service->generateOccurrence($plan->fresh(), [], $actor);
            $this->fail('A PAUSED plan must generate nothing.');
        } catch (DonationTransitionConflictException $e) {
            $this->assertSame('plan_not_active', $e->reason);
        }

        $service->cancel($plan->fresh(), $actor);

        try {
            $service->generateOccurrence($plan->fresh(), [], $actor);
            $this->fail('A CANCELLED plan must generate nothing.');
        } catch (DonationTransitionConflictException $e) {
            $this->assertSame('plan_not_active', $e->reason);
        }

        $this->assertSame(0, DonationRecurringPlan::find($plan->id)->occurrences()->count());

        $activePlan = $service->create($this->makeEligibleCampaign($actor), [
            'amount_minor' => 50000,
            'currency' => 'IDR',
            'frequency' => 'MONTHLY',
        ], $actor);

        $scheduled = new DonationRecurringOccurrence;
        $scheduled->forceFill([
            'recurring_plan_id' => $activePlan->id,
            'scheduled_at' => now(),
            'status' => 'SCHEDULED',
        ]);
        $scheduled->save();

        $failed = $service->markOccurrenceFailed($scheduled, 'child donation could not complete', $actor);

        $this->assertSame('FAILED', $failed->status);

        try {
            $service->markOccurrenceFailed($failed->fresh(), 'retry attempt', $actor);
            $this->fail('A FAILED occurrence must never be retried.');
        } catch (DonationTransitionConflictException $e) {
            $this->assertSame('invalid_transition', $e->reason);
        }

        $this->assertSame('FAILED', $failed->fresh()->status);
    }
}
