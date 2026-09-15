<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-005 — the single canonical public CMS path namespace (docs/implementation/
     * IMP-005-cms.md section 13 "cms_paths"). UNIQUE(active_path) is THE namespace
     * invariant across Pages, Articles, CURRENT claims and REDIRECT claims
     * simultaneously. UNIQUE(active_current_owner) bounds an identity to one active
     * CURRENT claim. Both uniques are expressed via STORED generated columns (CASE
     * WHEN status='ACTIVE' ...) because neither engine here supports a real partial/
     * filtered unique index (MySQL 8 has no partial-index syntax; NULLs simply never
     * collide on either engine, which is what makes the CASE-wrapped form behave
     * like one). active_current_owner tags the owner with 'P:'/'A:' because page_id
     * and article_id are independent sequences and could otherwise collide on the
     * same integer.
     *
     * The owner-tag concatenation syntax differs by driver (MySQL: CONCAT(); SQLite:
     * the `||` operator — MySQL's default sql_mode treats `||` as logical OR, not
     * concatenation), so this table is built with one raw CREATE TABLE per driver,
     * matching cms_content_revisions.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("CREATE TABLE cms_paths (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                path VARCHAR(191) NOT NULL,
                purpose VARCHAR(16) NOT NULL,
                page_id BIGINT UNSIGNED NULL,
                article_id BIGINT UNSIGNED NULL,
                revision_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(16) NOT NULL,
                released_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                active_path VARCHAR(191)
                    GENERATED ALWAYS AS (CASE WHEN status = 'ACTIVE' THEN path ELSE NULL END) STORED,
                active_current_owner VARCHAR(32) GENERATED ALWAYS AS (
                    CASE WHEN status = 'ACTIVE' AND purpose = 'CURRENT'
                        THEN CONCAT(CASE WHEN page_id IS NOT NULL THEN 'P' ELSE 'A' END, ':',
                                    COALESCE(page_id, article_id))
                        ELSE NULL END
                ) STORED,
                CONSTRAINT chk_cms_paths_owner_xor CHECK ((page_id IS NULL) <> (article_id IS NULL)),
                CONSTRAINT fk_cms_paths_page FOREIGN KEY (page_id) REFERENCES cms_pages (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cms_paths_article FOREIGN KEY (article_id) REFERENCES cms_articles (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cms_paths_revision_via_page
                    FOREIGN KEY (page_id, revision_id) REFERENCES cms_content_revisions (page_id, id),
                CONSTRAINT fk_cms_paths_revision_via_article
                    FOREIGN KEY (article_id, revision_id) REFERENCES cms_content_revisions (article_id, id),
                UNIQUE KEY cms_paths_active_path_unique (active_path),
                UNIQUE KEY cms_paths_active_current_owner_unique (active_current_owner),
                KEY cms_paths_purpose_status_index (purpose, status),
                KEY cms_paths_page_id_index (page_id),
                KEY cms_paths_article_id_index (article_id),
                KEY cms_paths_revision_id_index (revision_id)
            ) ENGINE=InnoDB");

            return;
        }

        // SQLite: the composite FKs above (revision_id paired per-owner) require
        // the same reference targets cms_content_revisions already exposes
        // (UNIQUE(page_id, id) / UNIQUE(article_id, id)); SQLite matches composite
        // FKs against any unique index, not only the primary key, so this is valid
        // without a "MATCH SIMPLE" clause (SQLite's only mode).
        DB::statement("CREATE TABLE cms_paths (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            path VARCHAR(191) NOT NULL,
            purpose VARCHAR(16) NOT NULL,
            page_id INTEGER NULL,
            article_id INTEGER NULL,
            revision_id INTEGER NOT NULL,
            status VARCHAR(16) NOT NULL,
            released_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            active_path VARCHAR(191)
                GENERATED ALWAYS AS (CASE WHEN status = 'ACTIVE' THEN path ELSE NULL END) STORED,
            active_current_owner VARCHAR(32) GENERATED ALWAYS AS (
                CASE WHEN status = 'ACTIVE' AND purpose = 'CURRENT'
                    THEN (CASE WHEN page_id IS NOT NULL THEN 'P' ELSE 'A' END) || ':' ||
                         COALESCE(page_id, article_id)
                    ELSE NULL END
            ) STORED,
            CHECK ((page_id IS NULL) <> (article_id IS NULL)),
            FOREIGN KEY (page_id) REFERENCES cms_pages (id),
            FOREIGN KEY (article_id) REFERENCES cms_articles (id),
            FOREIGN KEY (page_id, revision_id) REFERENCES cms_content_revisions (page_id, id),
            FOREIGN KEY (article_id, revision_id) REFERENCES cms_content_revisions (article_id, id),
            UNIQUE (active_path),
            UNIQUE (active_current_owner)
        )");

        DB::statement('CREATE INDEX cms_paths_purpose_status_index ON cms_paths (purpose, status)');
        DB::statement('CREATE INDEX cms_paths_page_id_index ON cms_paths (page_id)');
        DB::statement('CREATE INDEX cms_paths_article_id_index ON cms_paths (article_id)');
        DB::statement('CREATE INDEX cms_paths_revision_id_index ON cms_paths (revision_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_paths');
    }
};
