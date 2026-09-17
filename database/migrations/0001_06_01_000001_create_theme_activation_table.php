<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-006 — the single-row "currently active theme" pointer
     * (docs/implementation/IMP-006-theme-engine.md section 8/24 "Safe
     * switching"), mirroring cms_homepage_assignment's exact pattern:
     * CHECK (id = 1) makes "exactly one row" a real database invariant, the
     * row is seeded here so the lock target always exists (no INSERT race).
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('CREATE TABLE theme_activation (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                active_theme_id BIGINT UNSIGNED NULL,
                assigned_by_principal_id BIGINT UNSIGNED NULL,
                assigned_at DATETIME(6) NULL,
                updated_at DATETIME(6) NOT NULL,
                CONSTRAINT chk_theme_activation_singleton CHECK (id = 1),
                CONSTRAINT fk_theme_activation_theme
                    FOREIGN KEY (active_theme_id) REFERENCES themes (id) ON DELETE RESTRICT,
                CONSTRAINT fk_theme_activation_assigned_by
                    FOREIGN KEY (assigned_by_principal_id) REFERENCES principals (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB');
        } else {
            DB::statement('CREATE TABLE theme_activation (
                id INTEGER NOT NULL PRIMARY KEY,
                active_theme_id INTEGER NULL REFERENCES themes (id),
                assigned_by_principal_id INTEGER NULL REFERENCES principals (id),
                assigned_at DATETIME NULL,
                updated_at DATETIME NOT NULL,
                CHECK (id = 1)
            )');
        }

        DB::table('theme_activation')->insert([
            'id' => 1,
            'active_theme_id' => null,
            'assigned_by_principal_id' => null,
            'assigned_at' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_activation');
    }
};
