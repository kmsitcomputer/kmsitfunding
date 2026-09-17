<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-005 — versioned content payload + the lifecycle columns that drive
     * publication (docs/implementation/IMP-005-cms.md section 11 + section 13
     * "cms_content_revisions"). page_id/article_id is the canonical owner (exactly
     * one, real FKs — not a soft polymorphic pair). UNIQUE(page_id, id) /
     * UNIQUE(article_id, id) exist solely to be legal composite-FK reference targets
     * for the identity pointer FKs (added on cms_pages/cms_articles in the next
     * migration) and for cms_paths / cms_media_references.
     *
     * page_key/article_key COALESCE nullable owners to 0 so the per-owner
     * revision_no uniqueness is real on both engines (a UNIQUE index over a
     * nullable column never detects NULL-vs-NULL duplicates). active_draft_page_id /
     * active_draft_article_id are the DB-enforced "exactly one active DRAFT per
     * owner" invariant, using the same NULL-does-not-collide technique.
     *
     * Generated (STORED) columns and CHECK constraints must both exist at CREATE
     * TABLE time on SQLite (no post-creation ALTER support for either), so this
     * migration builds the table with one raw CREATE TABLE per driver rather than
     * the fluent Blueprint builder. MySQL and SQLite column types/expressions are
     * kept driver-appropriate; the column set and constraint set are identical.
     * CHECK constraints follow the same MySQL-only-in-house-precedent pattern as
     * 0001_03_01_000005_create_principals_table.php — the SQLite branch here goes
     * further and enforces them via CHECK too (SQLite supports CHECK at CREATE
     * TABLE time, unlike ALTER TABLE), so both engines get real DB enforcement;
     * section 29's raw-SQL DB-bypass tests are still scoped "MYSQL 8 REQUIRED" by
     * the specification for the composite-FK proofs, not for these CHECKs.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('CREATE TABLE cms_content_revisions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                page_id BIGINT UNSIGNED NULL,
                article_id BIGINT UNSIGNED NULL,
                revision_no BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                excerpt VARCHAR(511) NULL,
                article_type VARCHAR(16) NULL,
                slug_snapshot VARCHAR(191) NULL,
                body_html LONGTEXT NOT NULL,
                meta_title VARCHAR(255) NULL,
                meta_description VARCHAR(511) NULL,
                og_title VARCHAR(255) NULL,
                og_description VARCHAR(511) NULL,
                og_image_asset_id BIGINT UNSIGNED NULL,
                no_index TINYINT(1) NOT NULL DEFAULT 0,
                author_principal_id BIGINT UNSIGNED NOT NULL,
                authored_at DATETIME(6) NOT NULL,
                state VARCHAR(16) NOT NULL DEFAULT \'DRAFT\',
                published_at DATETIME(6) NULL,
                superseded_at DATETIME(6) NULL,
                state_changed_by_principal_id BIGINT UNSIGNED NULL,
                edit_version INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                page_key BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(page_id, 0)) STORED,
                article_key BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(article_id, 0)) STORED,
                active_draft_page_id BIGINT UNSIGNED
                    GENERATED ALWAYS AS (CASE WHEN state = \'DRAFT\' THEN page_id ELSE NULL END) STORED,
                active_draft_article_id BIGINT UNSIGNED
                    GENERATED ALWAYS AS (CASE WHEN state = \'DRAFT\' THEN article_id ELSE NULL END) STORED,
                CONSTRAINT chk_cms_content_revisions_owner_xor
                    CHECK ((page_id IS NULL) <> (article_id IS NULL)),
                CONSTRAINT chk_cms_content_revisions_article_type CHECK (
                    (page_id IS NOT NULL AND article_type IS NULL)
                    OR (article_id IS NOT NULL AND article_type IN (\'ARTICLE\', \'NEWS\'))
                ),
                CONSTRAINT fk_cms_content_revisions_page
                    FOREIGN KEY (page_id) REFERENCES cms_pages (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cms_content_revisions_article
                    FOREIGN KEY (article_id) REFERENCES cms_articles (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cms_content_revisions_og_image
                    FOREIGN KEY (og_image_asset_id) REFERENCES cms_media_assets (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cms_content_revisions_author
                    FOREIGN KEY (author_principal_id) REFERENCES principals (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cms_content_revisions_state_changed_by
                    FOREIGN KEY (state_changed_by_principal_id) REFERENCES principals (id) ON DELETE RESTRICT,
                UNIQUE KEY cms_content_revisions_page_id_id_unique (page_id, id),
                UNIQUE KEY cms_content_revisions_article_id_id_unique (article_id, id),
                UNIQUE KEY cms_content_revisions_owner_revno_unique (page_key, article_key, revision_no),
                UNIQUE KEY cms_content_revisions_active_draft_page_unique (active_draft_page_id),
                UNIQUE KEY cms_content_revisions_active_draft_article_unique (active_draft_article_id),
                KEY cms_content_revisions_state_index (state),
                KEY cms_content_revisions_article_key_state_index (article_key, state),
                KEY cms_content_revisions_published_at_index (published_at)
            ) ENGINE=InnoDB');

            return;
        }

        // SQLite (this repo's test environment): CHECK and GENERATED columns must
        // both be declared at CREATE TABLE time; there is no portable post-creation
        // ALTER for either on this driver.
        DB::statement('CREATE TABLE cms_content_revisions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            page_id INTEGER NULL REFERENCES cms_pages (id),
            article_id INTEGER NULL REFERENCES cms_articles (id),
            revision_no INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            excerpt VARCHAR(511) NULL,
            article_type VARCHAR(16) NULL,
            slug_snapshot VARCHAR(191) NULL,
            body_html TEXT NOT NULL,
            meta_title VARCHAR(255) NULL,
            meta_description VARCHAR(511) NULL,
            og_title VARCHAR(255) NULL,
            og_description VARCHAR(511) NULL,
            og_image_asset_id INTEGER NULL REFERENCES cms_media_assets (id),
            no_index TINYINT(1) NOT NULL DEFAULT 0,
            author_principal_id INTEGER NOT NULL REFERENCES principals (id),
            authored_at DATETIME NOT NULL,
            state VARCHAR(16) NOT NULL DEFAULT \'DRAFT\',
            published_at DATETIME NULL,
            superseded_at DATETIME NULL,
            state_changed_by_principal_id INTEGER NULL REFERENCES principals (id),
            edit_version INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            page_key INTEGER GENERATED ALWAYS AS (COALESCE(page_id, 0)) STORED,
            article_key INTEGER GENERATED ALWAYS AS (COALESCE(article_id, 0)) STORED,
            active_draft_page_id INTEGER
                GENERATED ALWAYS AS (CASE WHEN state = \'DRAFT\' THEN page_id ELSE NULL END) STORED,
            active_draft_article_id INTEGER
                GENERATED ALWAYS AS (CASE WHEN state = \'DRAFT\' THEN article_id ELSE NULL END) STORED,
            CHECK ((page_id IS NULL) <> (article_id IS NULL)),
            CHECK (
                (page_id IS NOT NULL AND article_type IS NULL)
                OR (article_id IS NOT NULL AND article_type IN (\'ARTICLE\', \'NEWS\'))
            ),
            UNIQUE (page_id, id),
            UNIQUE (article_id, id),
            UNIQUE (page_key, article_key, revision_no),
            UNIQUE (active_draft_page_id),
            UNIQUE (active_draft_article_id)
        )');

        DB::statement('CREATE INDEX cms_content_revisions_state_index ON cms_content_revisions (state)');
        DB::statement('CREATE INDEX cms_content_revisions_article_key_state_index ON cms_content_revisions (article_key, state)');
        DB::statement('CREATE INDEX cms_content_revisions_published_at_index ON cms_content_revisions (published_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_content_revisions');
    }
};
