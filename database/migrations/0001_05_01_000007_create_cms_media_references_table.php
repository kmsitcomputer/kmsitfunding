<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-005 — the canonical media reference model (docs/implementation/
     * IMP-005-cms.md section 13 "cms_media_references", IMP005-SPEC-05). The ONE
     * authoritative answer to "is this media asset used?" for every media
     * relationship CMS supports; deletion/archive/purge decisions read this table,
     * never parsed HTML. owner_revision_id is NOT NULL (every v1 media relationship
     * is carried by a revision payload field) and its two composite FKs (owner +
     * revision, matching cms_paths' pattern) make a reference row naming a revision
     * owned by a different identity — or the other identity kind — unwritable.
     * reference_kind is CHECK-paired with field_path per section 19's closed
     * vocabulary (body_html <-> BODY_TOKEN, og_image_asset_id <-> SEO_IMAGE).
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("CREATE TABLE cms_media_references (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                media_asset_id BIGINT UNSIGNED NOT NULL,
                owner_page_id BIGINT UNSIGNED NULL,
                owner_article_id BIGINT UNSIGNED NULL,
                owner_revision_id BIGINT UNSIGNED NOT NULL,
                field_path VARCHAR(64) NOT NULL,
                reference_kind VARCHAR(16) NOT NULL,
                status VARCHAR(16) NOT NULL,
                released_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                CONSTRAINT chk_cms_media_references_owner_xor
                    CHECK ((owner_page_id IS NULL) <> (owner_article_id IS NULL)),
                CONSTRAINT chk_cms_media_references_field_pairing CHECK (
                    (field_path = 'body_html' AND reference_kind = 'BODY_TOKEN')
                    OR (field_path = 'og_image_asset_id' AND reference_kind = 'SEO_IMAGE')
                ),
                CONSTRAINT fk_cms_media_references_asset
                    FOREIGN KEY (media_asset_id) REFERENCES cms_media_assets (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cms_media_references_owner_page
                    FOREIGN KEY (owner_page_id) REFERENCES cms_pages (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cms_media_references_owner_article
                    FOREIGN KEY (owner_article_id) REFERENCES cms_articles (id) ON DELETE RESTRICT,
                CONSTRAINT fk_cms_media_references_revision_via_page
                    FOREIGN KEY (owner_page_id, owner_revision_id) REFERENCES cms_content_revisions (page_id, id),
                CONSTRAINT fk_cms_media_references_revision_via_article
                    FOREIGN KEY (owner_article_id, owner_revision_id) REFERENCES cms_content_revisions (article_id, id),
                UNIQUE KEY cms_media_references_asset_revision_field_unique (media_asset_id, owner_revision_id, field_path),
                KEY cms_media_references_asset_status_index (media_asset_id, status),
                KEY cms_media_references_revision_status_index (owner_revision_id, status),
                KEY cms_media_references_owner_page_index (owner_page_id),
                KEY cms_media_references_owner_article_index (owner_article_id)
            ) ENGINE=InnoDB");

            return;
        }

        DB::statement("CREATE TABLE cms_media_references (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            media_asset_id INTEGER NOT NULL REFERENCES cms_media_assets (id),
            owner_page_id INTEGER NULL,
            owner_article_id INTEGER NULL,
            owner_revision_id INTEGER NOT NULL,
            field_path VARCHAR(64) NOT NULL,
            reference_kind VARCHAR(16) NOT NULL,
            status VARCHAR(16) NOT NULL,
            released_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CHECK ((owner_page_id IS NULL) <> (owner_article_id IS NULL)),
            CHECK (
                (field_path = 'body_html' AND reference_kind = 'BODY_TOKEN')
                OR (field_path = 'og_image_asset_id' AND reference_kind = 'SEO_IMAGE')
            ),
            FOREIGN KEY (owner_page_id) REFERENCES cms_pages (id),
            FOREIGN KEY (owner_article_id) REFERENCES cms_articles (id),
            FOREIGN KEY (owner_page_id, owner_revision_id) REFERENCES cms_content_revisions (page_id, id),
            FOREIGN KEY (owner_article_id, owner_revision_id) REFERENCES cms_content_revisions (article_id, id),
            UNIQUE (media_asset_id, owner_revision_id, field_path)
        )");

        DB::statement('CREATE INDEX cms_media_references_asset_status_index ON cms_media_references (media_asset_id, status)');
        DB::statement('CREATE INDEX cms_media_references_revision_status_index ON cms_media_references (owner_revision_id, status)');
        DB::statement('CREATE INDEX cms_media_references_owner_page_index ON cms_media_references (owner_page_id)');
        DB::statement('CREATE INDEX cms_media_references_owner_article_index ON cms_media_references (owner_article_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media_references');
    }
};
