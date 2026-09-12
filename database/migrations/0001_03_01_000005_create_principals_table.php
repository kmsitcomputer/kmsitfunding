<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-003 — canonical authorization-identity registry (see "Database
     * Contract > `principals`"). Every Role/Authority assignment references
     * exactly one row here via a real FK. Requires `users`,
     * `system_principals`, and `integration_principals` to already exist —
     * see the specification's "Migration Order".
     *
     * The CHECK constraint below encodes the tombstone-compatible lifecycle:
     *
     *   human + not tombstoned  -> human_user_id NOT NULL, other two NULL
     *   human + tombstoned      -> human_user_id NULL, other two NULL
     *   system                   -> human_user_id NULL, tombstoned_at NULL,
     *                                system_principal_id NOT NULL
     *   integration               -> human_user_id NULL, tombstoned_at NULL,
     *                                integration_principal_id NOT NULL
     *
     * MySQL 8 enforces this as a real database CHECK constraint. SQLite (used
     * only for this repository's local/test environment) cannot add a CHECK
     * constraint to an already-created table via ALTER TABLE — only at
     * original CREATE TABLE time, which Laravel's schema builder does not
     * expose a portable API for. The SAME invariant is therefore ALSO
     * enforced at the application layer (see App\Models\Rbac\Principal's
     * `saving` guard), which is required by the specification anyway
     * ("defense in depth... enforced at both the application and database
     * layer") — this is not a weaker substitute, it is the mandatory second
     * layer, with the MySQL CHECK as the authoritative production backstop.
     */
    public function up(): void
    {
        Schema::create('principals', function (Blueprint $table) {
            $table->id();
            $table->string('principal_kind');
            $table->foreignId('human_user_id')->nullable()->unique()
                ->constrained('users')->restrictOnDelete();
            $table->foreignId('system_principal_id')->nullable()->unique()
                ->constrained('system_principals')->restrictOnDelete();
            $table->foreignId('integration_principal_id')->nullable()->unique()
                ->constrained('integration_principals')->restrictOnDelete();
            $table->timestamp('tombstoned_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->index('principal_kind');
            $table->index('tombstoned_at');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE principals ADD CONSTRAINT chk_principals_kind_consistency CHECK (
                    (principal_kind = 'human' AND tombstoned_at IS NULL
                        AND human_user_id IS NOT NULL
                        AND system_principal_id IS NULL
                        AND integration_principal_id IS NULL)
                    OR
                    (principal_kind = 'human' AND tombstoned_at IS NOT NULL
                        AND human_user_id IS NULL
                        AND system_principal_id IS NULL
                        AND integration_principal_id IS NULL)
                    OR
                    (principal_kind = 'system'
                        AND human_user_id IS NULL
                        AND tombstoned_at IS NULL
                        AND system_principal_id IS NOT NULL
                        AND integration_principal_id IS NULL)
                    OR
                    (principal_kind = 'integration'
                        AND human_user_id IS NULL
                        AND tombstoned_at IS NULL
                        AND system_principal_id IS NULL
                        AND integration_principal_id IS NOT NULL)
                )
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('principals');
    }
};
