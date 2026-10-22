<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CR-001-B (Schema #5) — Zakat type registry (docs/change-requests/
     * CR-001-public-experience-cms-ziswaf-admin-v2.md Section 20/55).
     * `calculation_method_ref` is a reference key the future IMP-019
     * calculator dispatches on — never a formula or rate itself. No row is
     * seeded here (no Zakat type is invented by B).
     */
    public function up(): void
    {
        Schema::create('zakat_types', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 64)->unique();
            $table->string('name', 255);
            $table->string('calculation_method_ref', 64)->nullable();
            $table->boolean('is_active')->default(true);

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zakat_types');
    }
};
