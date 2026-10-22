<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CR-001-B (Schema #1/#2) — per-viewport navigation item visibility
     * targeting (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
     * Section 26). Additive only: nullable, DEFAULT TRUE preserves existing
     * rows' current (always-visible) behavior unchanged. Write owner of the
     * CONSUMING render logic (PublicRenderer/siteChrome) remains CR-001-E —
     * this migration only adds the columns.
     */
    public function up(): void
    {
        Schema::table('theme_navigation_items', function (Blueprint $table) {
            $table->boolean('visible_desktop')->default(true)->after('visible');
            $table->boolean('visible_mobile')->default(true)->after('visible_desktop');
        });
    }

    public function down(): void
    {
        Schema::table('theme_navigation_items', function (Blueprint $table) {
            $table->dropColumn(['visible_desktop', 'visible_mobile']);
        });
    }
};
