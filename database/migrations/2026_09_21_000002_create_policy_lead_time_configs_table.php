<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CR-001-B (Schema #12, HD-CR001-03) — the single-row configurable
     * minimum effective-date lead time for Zakat/Fidyah policy versions
     * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
     * Section 63). Mirrors theme_activation's exact singleton pattern:
     * CHECK (id = 1) makes "exactly one row" a real database invariant, the
     * row is seeded here with a NULL duration so no lead-time value is ever
     * hard-coded by this migration — an authorized actor must explicitly
     * configure it later.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('CREATE TABLE policy_lead_time_configs (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                lead_time_days INT UNSIGNED NULL,
                updated_by_principal_id BIGINT UNSIGNED NULL,
                updated_at DATETIME(6) NOT NULL,
                CONSTRAINT chk_policy_lead_time_configs_singleton CHECK (id = 1),
                CONSTRAINT fk_policy_lead_time_configs_updated_by
                    FOREIGN KEY (updated_by_principal_id) REFERENCES principals (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB');
        } else {
            DB::statement('CREATE TABLE policy_lead_time_configs (
                id INTEGER NOT NULL PRIMARY KEY,
                lead_time_days INTEGER NULL,
                updated_by_principal_id INTEGER NULL REFERENCES principals (id),
                updated_at DATETIME NOT NULL,
                CHECK (id = 1)
            )');
        }

        DB::table('policy_lead_time_configs')->insert([
            'id' => 1,
            'lead_time_days' => null,
            'updated_by_principal_id' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_lead_time_configs');
    }
};
