<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-006 — Template identity + content-kind assignment
     * (docs/implementation/IMP-006-theme-engine.md section 9). UNIQUE
     * (theme_id, content_kind) IS the assignment: at most one Template per
     * content kind per Theme. UNIQUE (theme_id, slug) keeps template slugs
     * distinct within a Theme (two Themes may each define their own 'home').
     */
    public function up(): void
    {
        Schema::create('theme_templates', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->string('slug', 100);
            $table->string('name', 255);
            $table->string('content_kind', 32);

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->unique(['theme_id', 'slug']);
            $table->unique(['theme_id', 'content_kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_templates');
    }
};
