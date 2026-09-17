<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-007 — Program: higher-level organizational/content context
     * (docs/implementation/IMP-007-campaign-program-fund.md section 8). No
     * default/system Program is ever seeded (HD-IMP007-04) — created_by/
     * updated_by are therefore NOT nullable, unlike themes.created_by_
     * principal_id (which accommodates a code-shipped default row).
     */
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('summary', 500)->nullable();
            $table->text('description_html')->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->unsignedInteger('edit_version')->default(0);

            $table->foreignId('created_by_principal_id')->constrained('principals')->restrictOnDelete();
            $table->foreignId('updated_by_principal_id')->constrained('principals')->restrictOnDelete();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
