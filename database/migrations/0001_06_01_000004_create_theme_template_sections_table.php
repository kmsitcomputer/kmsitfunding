<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-006 — the Template<->Section placement/ordering pivot
     * (docs/implementation/IMP-006-theme-engine.md section 10). Deleting a
     * Template cascades its placements (never the reusable Section itself,
     * which may be placed elsewhere); deleting a Section is application-
     * refused while any placement row references it (section 10 "reference-
     * count guard") — enforced by RESTRICT, matching IMP-005's own
     * reference-count discipline.
     */
    public function up(): void
    {
        Schema::create('theme_template_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_template_id')->constrained('theme_templates')->cascadeOnDelete();
            $table->foreignId('theme_section_id')->constrained('theme_sections')->restrictOnDelete();
            $table->unsignedInteger('position');

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->unique(['theme_template_id', 'position'], 'theme_template_sections_position_unique');
            $table->unique(['theme_template_id', 'theme_section_id'], 'theme_template_sections_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_template_sections');
    }
};
