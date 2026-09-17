<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * IMP-005 — adds the three composite "revision-pointer ownership" FKs on
     * cms_pages and cms_articles now that cms_content_revisions exists
     * (docs/implementation/IMP-005-cms.md section 13 "REVISION-POINTER OWNERSHIP").
     * Each FK is written child (owner id, pointer) -> parent (owner column, id),
     * owner column FIRST — the one direction convention used schema-wide (section 13
     * "Ownership-exact FKs"). This makes `Page A.published_revision_id = <revision
     * owned by Page B>` an errored write at the storage layer on every path,
     * including raw SQL, because the revision's page_id must equal the referencing
     * page's own id.
     *
     * MySQL and SQLite both support composite foreign keys; SQLite requires
     * `PRAGMA foreign_keys=ON` (Laravel's sqlite driver enables this by default) and
     * — unlike CHECK/generated columns — DOES support adding a new FK via a rebuilt
     * table, but Laravel's schema builder has no portable "ALTER TABLE ADD
     * CONSTRAINT FOREIGN KEY" for SQLite either. This migration therefore uses the
     * same driver-branch pattern as the previous two: a native ALTER on MySQL, and a
     * full table rebuild (SQLite's own documented procedure for adding a constraint
     * to an existing table) on SQLite.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE cms_pages
                ADD CONSTRAINT fk_cms_pages_latest_draft_revision
                    FOREIGN KEY (id, latest_draft_revision_id) REFERENCES cms_content_revisions (page_id, id),
                ADD CONSTRAINT fk_cms_pages_published_revision
                    FOREIGN KEY (id, published_revision_id) REFERENCES cms_content_revisions (page_id, id),
                ADD CONSTRAINT fk_cms_pages_scheduled_revision
                    FOREIGN KEY (id, scheduled_revision_id) REFERENCES cms_content_revisions (page_id, id)');

            DB::statement('ALTER TABLE cms_articles
                ADD CONSTRAINT fk_cms_articles_latest_draft_revision
                    FOREIGN KEY (id, latest_draft_revision_id) REFERENCES cms_content_revisions (article_id, id),
                ADD CONSTRAINT fk_cms_articles_published_revision
                    FOREIGN KEY (id, published_revision_id) REFERENCES cms_content_revisions (article_id, id),
                ADD CONSTRAINT fk_cms_articles_scheduled_revision
                    FOREIGN KEY (id, scheduled_revision_id) REFERENCES cms_content_revisions (article_id, id)');

            return;
        }

        // SQLite: rebuild each table with the composite FKs inline, per SQLite's
        // documented "12-step" ALTER procedure, condensed here because both tables
        // are still empty at this point in a fresh migration run (no data to copy
        // in application code — a real deployment migrating existing data would
        // need an explicit copy step, out of scope for this initial schema slice).
        DB::statement('PRAGMA foreign_keys=OFF');

        DB::statement('CREATE TABLE cms_pages_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ulid CHAR(26) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            status VARCHAR(16) NOT NULL,
            latest_draft_revision_id INTEGER NULL,
            published_revision_id INTEGER NULL,
            scheduled_revision_id INTEGER NULL,
            publish_at DATETIME NULL,
            unpublish_at DATETIME NULL,
            schedule_version INTEGER NOT NULL DEFAULT 0,
            scheduled_by_principal_id INTEGER NULL REFERENCES principals (id),
            scheduled_at DATETIME NULL,
            created_by_principal_id INTEGER NOT NULL REFERENCES principals (id),
            updated_by_principal_id INTEGER NOT NULL REFERENCES principals (id),
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (id, latest_draft_revision_id) REFERENCES cms_content_revisions (page_id, id),
            FOREIGN KEY (id, published_revision_id) REFERENCES cms_content_revisions (page_id, id),
            FOREIGN KEY (id, scheduled_revision_id) REFERENCES cms_content_revisions (page_id, id)
        )');
        DB::statement('INSERT INTO cms_pages_new SELECT
            id, ulid, title, status, latest_draft_revision_id, published_revision_id,
            scheduled_revision_id, publish_at, unpublish_at, schedule_version,
            scheduled_by_principal_id, scheduled_at, created_by_principal_id,
            updated_by_principal_id, created_at, updated_at
            FROM cms_pages');
        DB::statement('DROP TABLE cms_pages');
        DB::statement('ALTER TABLE cms_pages_new RENAME TO cms_pages');
        DB::statement('CREATE INDEX cms_pages_status_index ON cms_pages (status)');
        DB::statement('CREATE INDEX cms_pages_updated_at_index ON cms_pages (updated_at)');
        DB::statement('CREATE INDEX cms_pages_publish_at_id_index ON cms_pages (publish_at, id)');
        DB::statement('CREATE INDEX cms_pages_unpublish_at_id_index ON cms_pages (unpublish_at, id)');

        DB::statement('CREATE TABLE cms_articles_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ulid CHAR(26) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            status VARCHAR(16) NOT NULL,
            excerpt VARCHAR(511) NULL,
            first_published_at DATETIME NULL,
            latest_draft_revision_id INTEGER NULL,
            published_revision_id INTEGER NULL,
            scheduled_revision_id INTEGER NULL,
            publish_at DATETIME NULL,
            unpublish_at DATETIME NULL,
            schedule_version INTEGER NOT NULL DEFAULT 0,
            scheduled_by_principal_id INTEGER NULL REFERENCES principals (id),
            scheduled_at DATETIME NULL,
            created_by_principal_id INTEGER NOT NULL REFERENCES principals (id),
            updated_by_principal_id INTEGER NOT NULL REFERENCES principals (id),
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (id, latest_draft_revision_id) REFERENCES cms_content_revisions (article_id, id),
            FOREIGN KEY (id, published_revision_id) REFERENCES cms_content_revisions (article_id, id),
            FOREIGN KEY (id, scheduled_revision_id) REFERENCES cms_content_revisions (article_id, id)
        )');
        DB::statement('INSERT INTO cms_articles_new SELECT
            id, ulid, title, status, excerpt, first_published_at, latest_draft_revision_id,
            published_revision_id, scheduled_revision_id, publish_at, unpublish_at,
            schedule_version, scheduled_by_principal_id, scheduled_at,
            created_by_principal_id, updated_by_principal_id, created_at, updated_at
            FROM cms_articles');
        DB::statement('DROP TABLE cms_articles');
        DB::statement('ALTER TABLE cms_articles_new RENAME TO cms_articles');
        DB::statement('CREATE INDEX cms_articles_status_index ON cms_articles (status)');
        DB::statement('CREATE INDEX cms_articles_updated_at_index ON cms_articles (updated_at)');
        DB::statement('CREATE INDEX cms_articles_publish_at_id_index ON cms_articles (publish_at, id)');
        DB::statement('CREATE INDEX cms_articles_unpublish_at_id_index ON cms_articles (unpublish_at, id)');

        DB::statement('PRAGMA foreign_keys=ON');
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE cms_pages
                DROP FOREIGN KEY fk_cms_pages_latest_draft_revision,
                DROP FOREIGN KEY fk_cms_pages_published_revision,
                DROP FOREIGN KEY fk_cms_pages_scheduled_revision');

            DB::statement('ALTER TABLE cms_articles
                DROP FOREIGN KEY fk_cms_articles_latest_draft_revision,
                DROP FOREIGN KEY fk_cms_articles_published_revision,
                DROP FOREIGN KEY fk_cms_articles_scheduled_revision');

            return;
        }

        // SQLite rollback of a table rebuild is not attempted here: a `down()` that
        // needs to remove FKs from an already-rebuilt SQLite table would itself
        // require another full rebuild. This migration is not expected to be rolled
        // back in isolation on SQLite; roll back cms_content_revisions and this
        // migration together (drop the whole CMS schema slice) instead.
        throw new RuntimeException(
            'Rolling back this migration alone on SQLite is not supported — roll back the whole IMP-005 schema slice instead.'
        );
    }
};
