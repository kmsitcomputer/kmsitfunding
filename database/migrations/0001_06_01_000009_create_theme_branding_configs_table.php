<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-006 — one validated, versioned branding record per Theme
     * (docs/implementation/IMP-006-theme-engine.md section 16). `color_tokens`
     * is a JSON object over the CLOSED token-name set (validated server-side
     * before every save — never a free key-value bag).
     */
    public function up(): void
    {
        Schema::create('theme_branding_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_id')->unique()->constrained('themes')->cascadeOnDelete();
            $table->json('color_tokens');
            $table->string('font_family', 100)->default('system');
            $table->foreignId('logo_theme_asset_id')->nullable()->constrained('theme_assets')->nullOnDelete();
            $table->foreignId('favicon_theme_asset_id')->nullable()->constrained('theme_assets')->nullOnDelete();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_branding_configs');
    }
};
