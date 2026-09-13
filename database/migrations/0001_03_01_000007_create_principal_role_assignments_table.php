<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-003 — binds a Role to a Principal within a Scope, for a bounded
     * effective period. See "Database Contract > `principal_role_assignments`"
     * and "Scope Uniqueness Normalization + MySQL 8 Active-Assignment
     * Uniqueness".
     *
     * scope_type/scope_id CHECK: enforced via MySQL CHECK where available
     * (see the `principals` migration's comment on the SQLite limitation and
     * the application-level guard that covers the same invariant on every
     * driver).
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        $activeAssignmentExpression = $driver === 'sqlite'
            ? "case when revoked_at is null then (principal_id || ':' || role_id || ':' || scope_type || ':' || ifnull(scope_id, 0)) else null end"
            : "case when revoked_at is null then concat(principal_id, ':', role_id, ':', scope_type, ':', ifnull(scope_id, 0)) else null end";

        Schema::create('principal_role_assignments', function (Blueprint $table) use ($activeAssignmentExpression) {
            $table->id();
            $table->foreignId('principal_id')->constrained('principals');
            $table->foreignId('role_id')->constrained('roles');
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('assigned_by_principal_id')->nullable()->constrained('principals');
            $table->foreignId('revoked_by_principal_id')->nullable()->constrained('principals');
            $table->timestamps();

            $table->string('active_assignment_key', 128)->nullable()->storedAs($activeAssignmentExpression);

            $table->index('principal_id');
            $table->index('role_id');
            $table->index(['scope_type', 'scope_id']);
            $table->unique('active_assignment_key');
        });

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE principal_role_assignments ADD CONSTRAINT chk_pra_scope_matrix CHECK (
                    (scope_type IN ('GLOBAL_PLATFORM', 'ORGANIZATION', 'OWN') AND scope_id IS NULL)
                    OR
                    (scope_type IN ('PARTNER', 'CAMPAIGN', 'PROGRAM', 'FUND', 'FUNDRAISER',
                        'BENEFICIARY_CASE', 'ASSIGNED_WORK') AND scope_id IS NOT NULL)
                )
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('principal_role_assignments');
    }
};
