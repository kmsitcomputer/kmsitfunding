<?php

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;

/**
 * IMP004-IMPL-M01 test-infrastructure fix: RolePermissionService::grant()
 * enforces the restored Transaction Ownership Invariant
 * (`DB::connection()->transactionLevel() !== 0` must be false at call
 * time), so no wrapping transaction may be left open during a test body.
 * Neither built-in trait fits this repository's `:memory:` SQLite test
 * connection: `RefreshDatabase` always leaves one open for the whole test
 * body (that IS the incompatibility Codex's finding requires resolving on
 * the test side, never by weakening the invariant); `DatabaseTruncation`
 * never restores the cached in-memory PDO connection between tests, so
 * every test after the first gets a brand-new, unmigrated `:memory:`
 * database (its migrate-once static flag is already tripped).
 *
 * This trait migrates once and restores that same connection on every
 * subsequent test, then truncates all tables directly — leaving the
 * connection at transaction level 0 for the test body, exactly like
 * `DatabaseTruncation` does for a persistent database. It deliberately
 * keeps its OWN private static PDO cache/migrated-flag rather than reusing
 * Laravel's `RefreshDatabaseState` (which `RefreshDatabase` also reads and
 * writes): sharing that state caused committed rows left by one of THESE
 * tests to leak into a later, unrelated `RefreshDatabase`-based test class
 * in the same process (that trait restores the same cached PDO but only
 * ever wraps-and-rolls-back — it never truncates — so it saw this trait's
 * leftover data). Keeping a separate cache means each mechanism manages its
 * own independent in-memory `:memory:` database, and the two can never
 * collide no matter what order test classes run in.
 */
trait TruncatesInMemorySqlite
{
    private static bool $truncatesInMemorySqliteMigrated = false;

    private static ?\PDO $truncatesInMemorySqliteCachedPdo = null;

    protected function setUpTruncatedDatabase(): void
    {
        $database = $this->app->make('db');
        $connectionName = config('database.default');
        $connection = $database->connection($connectionName);
        $isInMemory = config("database.connections.{$connectionName}.database") === ':memory:';

        if ($isInMemory && self::$truncatesInMemorySqliteCachedPdo !== null) {
            $connection->setPdo(self::$truncatesInMemorySqliteCachedPdo);
        }

        if (! self::$truncatesInMemorySqliteMigrated) {
            $this->artisan('migrate:fresh');

            $this->app[Kernel::class]->setArtisan(null);

            self::$truncatesInMemorySqliteMigrated = true;

            if ($isInMemory) {
                self::$truncatesInMemorySqliteCachedPdo = $connection->getPdo();
            }

            return;
        }

        $this->truncateAllTablesFor($connection);
    }

    /**
     * Against a real persistent database (MySQL), leftover committed rows
     * from one of THESE tests would otherwise remain visible to whatever
     * test runs next — including an unrelated `RefreshDatabase`-based test
     * class sharing the same physical testing database, which only ever
     * wraps-and-rolls-back and never truncates, so it would see this test's
     * leftover data. Truncating on tearDown as well (not only on the next
     * setUp) guarantees the shared connection is always left clean for
     * whatever runs next, regardless of which mechanism that is. A no-op
     * cost for SQLite's isolated `:memory:` world, but required for MySQL.
     */
    protected function tearDownTruncatedDatabase(): void
    {
        $connectionName = config('database.default');
        $this->truncateAllTablesFor($this->app->make('db')->connection($connectionName));
    }

    private function truncateAllTablesFor(ConnectionInterface $connection): void
    {
        $migrationsTable = config('database.migrations.table', 'migrations');

        $connection->getSchemaBuilder()->withoutForeignKeyConstraints(function () use ($connection, $migrationsTable) {
            foreach ($connection->getSchemaBuilder()->getTableListing(schemaQualified: false) as $table) {
                if ($table === $migrationsTable) {
                    continue;
                }

                $connection->table($table)->truncate();
            }
        });

        $this->reseedSingletonRowsFor($connection);
    }

    /**
     * IMP005-FINAL-GATE-class fix (discovered while implementing IMP-006):
     * `cms_homepage_assignment` (IMP-005) and `theme_activation` (IMP-006)
     * are singleton tables whose one required row (id=1) is inserted ONLY
     * by their own migration's `up()` — never reseeded by a database
     * seeder. This trait's blanket truncate-everything approach silently
     * deleted that row with no way to restore it for the remainder of the
     * test process (this trait's own `migrate:fresh` runs only once,
     * gated by a static flag), permanently breaking any LATER
     * `RefreshDatabase`-based test — in the SAME `phpunit` process — that
     * depends on either singleton row existing (both services use
     * `whereKey(1)->lockForUpdate()->firstOrFail()`, so a missing row is a
     * hard failure, not a graceful empty state). Restoring both rows here,
     * immediately after every truncate, keeps this trait's own tests
     * correctly isolated while no longer leaving the shared MySQL testing
     * database in a state that silently breaks unrelated test classes
     * ordered after them. Table existence is checked so this trait remains
     * usable on a schema predating either table.
     */
    private function reseedSingletonRowsFor(ConnectionInterface $connection): void
    {
        $tables = $connection->getSchemaBuilder()->getTableListing(schemaQualified: false);

        if (in_array('cms_homepage_assignment', $tables, true)) {
            $connection->table('cms_homepage_assignment')->insert([
                'id' => 1,
                'page_id' => null,
                'assigned_by_principal_id' => null,
                'assigned_at' => null,
                'updated_at' => now(),
            ]);
        }

        if (in_array('theme_activation', $tables, true)) {
            $connection->table('theme_activation')->insert([
                'id' => 1,
                'active_theme_id' => null,
                'assigned_by_principal_id' => null,
                'assigned_at' => null,
                'updated_at' => now(),
            ]);
        }
    }
}
