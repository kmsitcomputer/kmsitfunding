<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CR-001-B (Schema #8) — dated, admin-entered gold price snapshot
     * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
     * Section 20/55): "Gold prices ADDED as admin-entered historical
     * snapshots, never a live float API trust". `amount_minor` follows
     * canonical money discipline (Section 64) — integer minor units, never
     * FLOAT/DOUBLE. No row is seeded — no price is invented by B.
     */
    public function up(): void
    {
        Schema::create('gold_price_references', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->date('as_of_date');
            $table->string('source_ref', 255)->nullable();

            $table->foreignId('created_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();

            $table->dateTime('created_at', 6);

            $table->unique(['currency', 'as_of_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gold_price_references');
    }
};
