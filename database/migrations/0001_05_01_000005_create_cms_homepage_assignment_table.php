<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-005 — the single-row homepage CONTENT designation (docs/implementation/
     * IMP-005-cms.md section 13 "cms_homepage_assignment"). A designation, not a
     * route: `/` stays application-owned. CHECK (id = 1) makes "exactly one row is
     * representable" a real database invariant (Pattern B of the remediation
     * options — a dedicated singleton row always exists to lock, so every
     * assignment/replacement/clear serializes on it). The one row is seeded here,
     * in the same migration, so the lock target always exists — no INSERT race.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('CREATE TABLE cms_homepage_assignment (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                page_id BIGINT UNSIGNED NULL,
                assigned_by_principal_id BIGINT UNSIGNED NULL,
                assigned_at DATETIME(6) NULL,
                updated_at DATETIME(6) NOT NULL,
                CONSTRAINT chk_cms_homepage_assignment_singleton CHECK (id = 1),
                CONSTRAINT fk_cms_homepage_assignment_page
                    FOREIGN KEY (page_id) REFERENCES cms_pages (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cms_homepage_assignment_assigned_by
                    FOREIGN KEY (assigned_by_principal_id) REFERENCES principals (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB');
        } else {
            DB::statement('CREATE TABLE cms_homepage_assignment (
                id INTEGER NOT NULL PRIMARY KEY,
                page_id INTEGER NULL REFERENCES cms_pages (id),
                assigned_by_principal_id INTEGER NULL REFERENCES principals (id),
                assigned_at DATETIME NULL,
                updated_at DATETIME NOT NULL,
                CHECK (id = 1)
            )');
        }

        DB::table('cms_homepage_assignment')->insert([
            'id' => 1,
            'page_id' => null,
            'assigned_by_principal_id' => null,
            'assigned_at' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_homepage_assignment');
    }
};
