<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-006 — Theme identity + lifecycle (docs/implementation/
     * IMP-006-theme-engine.md section 7/8). `is_system_default` marks the
     * single, code-shipped, never-deletable fallback theme (section 8
     * "Fallback behavior") — seeded by a later migration in this same batch,
     * once the table exists.
     */
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->string('status', 16)->default('DRAFT');
            $table->boolean('is_system_default')->default(false);

            // Nullable: the code-shipped System Default Theme (section 8
            // "Fallback behavior") is seeded by migration, not created
            // through the admin API, so it has no attributable Human actor.
            // Every Theme created through ThemeService::create() always
            // supplies a real actor — this column is never left null by
            // application code.
            $table->foreignId('created_by_principal_id')->nullable()
                ->constrained('principals')->restrictOnDelete();
            $table->foreignId('updated_by_principal_id')->nullable()
                ->constrained('principals')->restrictOnDelete();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
