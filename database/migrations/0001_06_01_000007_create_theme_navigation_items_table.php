<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-006 — navigation items (docs/implementation/IMP-006-theme-engine.md
     * section 14). `destination_type` is the closed union
     * SYSTEM_ROUTE|CMS_CONTENT|EXTERNAL_URL; exactly one of the three
     * destination_* columns is populated per row, enforced at the
     * application layer (never a raw arbitrary URL column doing double
     * duty). `parent_id` self-FK is restricted to ONE level of nesting by
     * the application layer (a child may never itself have children) — not
     * a DB-representable constraint, matching IMP-005's own precedent of
     * enforcing structural rules the DB can't express directly in the
     * service layer.
     */
    public function up(): void
    {
        Schema::create('theme_navigation_items', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('theme_navigation_menu_id')->constrained('theme_navigation_menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('theme_navigation_items')->cascadeOnDelete();
            $table->string('label', 255);
            $table->string('destination_type', 16);
            $table->string('destination_route', 255)->nullable();
            $table->string('destination_content_kind', 16)->nullable();
            $table->char('destination_content_ulid', 26)->nullable();
            $table->string('destination_external_url', 2048)->nullable();
            $table->unsignedInteger('position');
            $table->boolean('visible')->default(true);

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->unique(['theme_navigation_menu_id', 'parent_id', 'position'], 'theme_nav_items_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_navigation_items');
    }
};
