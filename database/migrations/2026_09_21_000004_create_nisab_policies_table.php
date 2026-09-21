<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CR-001-B (Schema #7) — versioned Nisab threshold basis reference
     * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
     * Section 20/55). `basis_code` identifies which physical/monetary basis
     * the threshold is expressed against (e.g. a gold-weight reference) —
     * a reference key, not an invented rate. `gram_equivalent` is a
     * physical quantity and therefore DECIMAL (never FLOAT/DOUBLE, never
     * amount_minor). No default/rate value is seeded — only the versioned
     * table structure is created; an authorized actor populates real rows.
     */
    public function up(): void
    {
        Schema::create('nisab_policies', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('basis_code', 64);
            $table->decimal('gram_equivalent', 10, 4)->nullable();
            $table->string('source_ref', 255)->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status', 24);

            $table->foreignId('created_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();
            $table->foreignId('updated_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index(['basis_code', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nisab_policies');
    }
};
