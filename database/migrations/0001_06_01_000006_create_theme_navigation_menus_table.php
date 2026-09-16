<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-006 — named navigation menus (docs/implementation/
     * IMP-006-theme-engine.md section 14, Q30). Presentation only — no
     * relationship to route authorization (section 6).
     */
    public function up(): void
    {
        Schema::create('theme_navigation_menus', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name', 255);

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->unique(['theme_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_navigation_menus');
    }
};
