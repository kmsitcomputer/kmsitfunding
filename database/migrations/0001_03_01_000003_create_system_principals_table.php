<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-003 — fixed, seeded, non-authenticatable System Principal catalog
     * (SECURITY-ARCHITECTURE.md). One row per distinct background-job
     * identity needing its own auditable authorization context. Empty at
     * this stage — later stages add their own rows.
     */
    public function up(): void
    {
        Schema::create('system_principals', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_principals');
    }
};
