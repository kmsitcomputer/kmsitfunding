<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-006 — Section identity (docs/implementation/IMP-006-theme-engine.md
     * section 10). A Section's PLACEMENT (which Template, at what position)
     * lives in the theme_template_sections pivot (next migration), not here —
     * this is what lets a `is_reusable` Section be placed into more than one
     * Template without being copied.
     */
    public function up(): void
    {
        Schema::create('theme_sections', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->boolean('is_reusable')->default(false);
            $table->string('layout_variant', 64)->default('default');
            $table->boolean('visible')->default(true);

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_sections');
    }
};
