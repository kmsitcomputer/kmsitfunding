<?php

namespace Tests\Feature\Payment;

use App\Models\Donation\Donation;
use App\Models\Payment\Payment;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Donation\DonationService;
use App\Services\Payment\Exceptions\PaymentTransitionConflictException;
use App\Services\Payment\PaymentCreationService;
use App\Services\Payment\PaymentTransitionService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Donation\MakesDonationCampaigns;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-009 — genuine MySQL concurrency + schema evidence
 * (docs/implementation/IMP-009-payment-hub.md "Concurrency" /
 * "Testing Requirements", HD-IMP009-01, AC-009-019/021): REAL
 * disposable MySQL required — SQLite does not provide genuine
 * row-locking/transaction-isolation behavior.
 *
 * Each test creates GENUINE overlap with two separate database
 * connections to the same disposable database: a contender
 * connection opens a transaction and holds a row lock (or an
 * uncommitted unique insert) WITHOUT committing, while the primary
 * connection attempts the competing write with a short
 * innodb_lock_wait_timeout. A lock-wait-timeout (MySQL 1205) on the
 * primary is impossible under sequential execution — it is the
 * smoking-gun evidence the two operations genuinely overlapped and
 * contended. No sequential pair of calls is labelled concurrency
 * here.
 *
 * Uses a dedicated disposable database (kmsitdonation_imp009_test),
 * NEVER the live/development kmsitdonation database (asserted in
 * setUp — the run aborts otherwise). Skipped automatically when the
 * disposable MySQL database is not reachable.
 */
class PaymentConcurrencyTest extends TestCase
{
    use MakesDonationCampaigns;
    use RbacTestActors;

    private const MYSQL_DATABASE = 'kmsitdonation_imp009_test';

    private const DEV_DATABASE = 'kmsitdonation';

    private const CONTENDER_CONNECTION = 'mysql_contender';

    private const LOCK_WAIT_SECONDS = 3;

    private bool $usesDisposableMysql = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->disposableMysqlAvailable()) {
            $this->markTestSkipped('Disposable MySQL (kmsitdonation_imp009_test) is not reachable — concurrency evidence requires real MySQL.');
        }

        $this->assertNotSame(
            self::DEV_DATABASE,
            config('database.connections.mysql.database'),
            'Refusing to run destructive concurrency tests against the development database.'
        );

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

    private function makeSystemActor(): Principal
    {
        $catalog = SystemPrincipal::firstOrCreate(
            ['code' => 'test.payment.race'],
            ['description' => 'Test racing payment identity.']
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
            'guest_email' => 'g-'.uniqid().'@example.com',
        ], null, $key);
    }

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
     * A. Two genuinely overlapping same-key creates: exactly one
     * Payment row exists and the legitimate replay resolves to it.
     */
    public function test_concurrent_same_key_create_creates_exactly_one_row(): void
    {
        $donation = $this->makeGuestDonation('race-don-'.uniqid());
        $key = 'race-idem-'.uniqid();

        $contender = DB::connection(self::CONTENDER_CONNECTION);
        $contender->beginTransaction();
        $contender->table('payments')->insert([
            'ulid' => (string) Str::ulid(),
            'donation_id' => $donation->id,
            'provider' => 'manual_transfer',
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'status' => 'PENDING',
            'idempotency_key' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->useShortLockWait();

        try {
            app(PaymentCreationService::class)->create($donation, ['provider' => 'manual_transfer'], null, $key);
            $this->fail('The overlapping same-key create must block on the contender-held unique entry.');
        } catch (QueryException $e) {
            $this->assertLockWaitTimeout($e);
        } finally {
            $this->releaseContender(commit: true);
        }

        $replayed = app(PaymentCreationService::class)->create($donation, ['provider' => 'manual_transfer'], null, $key);

        $this->assertSame(1, Payment::query()->where('idempotency_key', $key)->count());
        $this->assertSame(Payment::query()->where('idempotency_key', $key)->first()->id, $replayed->id);
    }

    /**
     * B. Concurrent differently-keyed creations against the same
     * Donation: the HD-IMP009-01 one-ACTIVE-attempt invariant holds —
     * the loser observes the typed conflict, final state is exactly
     * one ACTIVE row.
     */
    public function test_concurrent_creations_against_one_donation_yield_exactly_one_active_attempt(): void
    {
        $donation = $this->makeGuestDonation('race-don-b-'.uniqid());

        app(PaymentCreationService::class)->create($donation, ['provider' => 'manual_transfer'], null, 'race-b1-'.uniqid());

        // Genuine overlap: the contender holds the Donation row lock
        // while the primary attempts a second creation with a short
        // lock wait — a 1205 here proves real contention on the
        // Donation-then-Payment lock path.
        $this->holdRowLock('donations', $donation->id);
        $this->useShortLockWait();

        try {
            app(PaymentCreationService::class)->create($donation, ['provider' => 'manual_transfer'], null, 'race-b2-'.uniqid());
            $this->fail('The overlapping second creation must block on the contender-held Donation lock.');
        } catch (QueryException $e) {
            $this->assertLockWaitTimeout($e);
        } finally {
            $this->releaseContender();
        }

        // After release, the service-layer guard rejects the second
        // ACTIVE attempt with the typed conflict — exactly one ACTIVE
        // row, never two.
        try {
            app(PaymentCreationService::class)->create($donation, ['provider' => 'manual_transfer'], null, 'race-b3-'.uniqid());
            $this->fail('A second ACTIVE attempt must be rejected.');
        } catch (PaymentTransitionConflictException $e) {
            $this->assertSame('active_attempt_exists', $e->reason);
        }

        $this->assertSame(1, Payment::query()
            ->where('donation_id', $donation->id)
            ->whereIn('status', ['PENDING', 'REQUIRES_ACTION'])
            ->count());
    }

    /**
     * C. DB-level backstop, bypassing the service layer: a raw second
     * ACTIVE row for the same donation_id violates
     * UNIQUE(donation_id, active_slot) — HD-IMP009-01's deterministic
     * guarantee independent of application lock discipline.
     */
    public function test_active_slot_unique_backstop_rejects_a_raw_second_active_row(): void
    {
        $donation = $this->makeGuestDonation('race-don-c-'.uniqid());

        DB::table('payments')->insert([
            'ulid' => (string) Str::ulid(),
            'donation_id' => $donation->id,
            'provider' => 'manual_transfer',
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'status' => 'PENDING',
            'idempotency_key' => 'race-c1-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::table('payments')->insert([
                'ulid' => (string) Str::ulid(),
                'donation_id' => $donation->id,
                'provider' => 'manual_transfer',
                'amount_minor' => 10000,
                'currency' => 'IDR',
                'status' => 'REQUIRES_ACTION',
                'idempotency_key' => 'race-c2-'.uniqid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('The DB backstop must reject a second ACTIVE row.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1] ?? null);
        }

        // Terminal rows never collide — the slot frees on terminal.
        DB::table('payments')
            ->where('donation_id', $donation->id)
            ->update(['status' => 'FAILED', 'updated_at' => now()]);

        DB::table('payments')->insert([
            'ulid' => (string) Str::ulid(),
            'donation_id' => $donation->id,
            'provider' => 'manual_transfer',
            'amount_minor' => 10000,
            'currency' => 'IDR',
            'status' => 'PENDING',
            'idempotency_key' => 'race-c3-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, Payment::query()
            ->where('donation_id', $donation->id)
            ->whereIn('status', ['PENDING', 'REQUIRES_ACTION'])
            ->count());
    }

    /**
     * D. Concurrent identical terminal transitions on one PENDING
     * Payment: exactly one succeeds; the racing loser observes the
     * typed conflict; final state is exactly one terminal state.
     */
    public function test_two_overlapping_terminal_transitions_exactly_one_succeeds(): void
    {
        $donation = $this->makeGuestDonation('race-don-d-'.uniqid());
        $payment = app(PaymentCreationService::class)->create(
            $donation, ['provider' => 'manual_transfer'], null, 'race-d-'.uniqid()
        );
        $system = $this->makeSystemActor();
        $service = app(PaymentTransitionService::class);

        $this->holdRowLock('payments', $payment->id);
        $this->useShortLockWait();

        try {
            $service->markSucceeded(Payment::query()->whereKey($payment->id)->first(), $system);
            $this->fail('The overlapping transition must block on the contender-held row lock.');
        } catch (QueryException $e) {
            $this->assertLockWaitTimeout($e);
        } finally {
            $this->releaseContender();
        }

        $service->markSucceeded($payment->fresh(), $system);

        try {
            $service->markFailed(Payment::query()->whereKey($payment->id)->first(), $system);
            $this->fail('A transition off a terminal Payment must be rejected with a typed conflict.');
        } catch (PaymentTransitionConflictException $e) {
            $this->assertSame('invalid_transition', $e->reason);
        }

        $this->assertSame('SUCCEEDED', $payment->fresh()->status);
    }
}
