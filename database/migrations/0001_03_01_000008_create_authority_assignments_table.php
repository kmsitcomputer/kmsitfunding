<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-003 — binds an Authority Type to a Principal within a Scope, for a
     * bounded period. See "Database Contract > `authority_assignments`".
     * `assigned_by_principal_id` is NOT NULL unconditionally — the Q25
     * Bridge never creates a row here (it grants only the super_admin Role),
     * so every Authority Assignment always has a genuine, distinct grantor.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        $activeAssignmentExpression = $driver === 'sqlite'
            ? "case when revoked_at is null then (principal_id || ':' || authority_type_id || ':' || scope_type || ':' || ifnull(scope_id, 0)) else null end"
            : "case when revoked_at is null then concat(principal_id, ':', authority_type_id, ':', scope_type, ':', ifnull(scope_id, 0)) else null end";

        Schema::create('authority_assignments', function (Blueprint $table) use ($activeAssignmentExpression) {
            $table->id();
            $table->foreignId('authority_type_id')->constrained('authority_types');
            $table->foreignId('principal_id')->constrained('principals');
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('assigned_by_principal_id')->constrained('principals');
            $table->foreignId('revoked_by_principal_id')->nullable()->constrained('principals');
            $table->timestamps();

            $table->string('active_assignment_key', 128)->nullable()->storedAs($activeAssignmentExpression);

            $table->index('principal_id');
            $table->index('authority_type_id');
            $table->index(['scope_type', 'scope_id']);
            $table->unique('active_assignment_key');
        });

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE authority_assignments ADD CONSTRAINT chk_aa_scope_matrix CHECK (
                    (scope_type IN ('GLOBAL_PLATFORM', 'ORGANIZATION', 'OWN') AND scope_id IS NULL)
                    OR
                    (scope_type IN ('PARTNER', 'CAMPAIGN', 'PROGRAM', 'FUND', 'FUNDRAISER',
                        'BENEFICIARY_CASE', 'ASSIGNED_WORK') AND scope_id IS NOT NULL)
                )
                SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE authority_assignments ADD CONSTRAINT chk_aa_no_self_grant
                    CHECK (assigned_by_principal_id <> principal_id)
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('authority_assignments');
    }
};
