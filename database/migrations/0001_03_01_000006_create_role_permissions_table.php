<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-003 — Role -> Permission grant, with durable (never hard-deleted)
     * history. `active_grant_key` is a STORED generated column, non-null
     * only while the grant is active (revoked_at IS NULL), giving a
     * MySQL-8-compatible unique-active-grant constraint with no NOW()
     * dependency — see the specification's "Scope Uniqueness Normalization +
     * MySQL 8 Active-Assignment Uniqueness".
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        $activeGrantExpression = $driver === 'sqlite'
            ? "case when revoked_at is null then (role_id || ':' || permission_id) else null end"
            : "case when revoked_at is null then concat(role_id, ':', permission_id) else null end";

        Schema::create('role_permissions', function (Blueprint $table) use ($activeGrantExpression) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles');
            $table->foreignId('permission_id')->constrained('permissions');
            $table->timestamp('granted_at');
            $table->foreignId('granted_by_principal_id')->nullable()->constrained('principals');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_principal_id')->nullable()->constrained('principals');
            $table->timestamps();

            $table->string('active_grant_key', 64)->nullable()->storedAs($activeGrantExpression);

            $table->index('role_id');
            $table->index('permission_id');
            $table->unique('active_grant_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
