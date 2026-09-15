<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-005 — content media library (docs/implementation/IMP-005-cms.md section 13
     * "cms_media_assets"). Created before cms_content_revisions in this migration
     * sequence (though it appears later in the specification's own narrative order)
     * because cms_content_revisions.og_image_asset_id is a real FK to this table and
     * must be able to reference it at creation time. status is ACTIVE | ARCHIVED |
     * PURGED (three states — logical archive and physical purge are different facts,
     * see section 27); archived_by_principal_id is a column (not re-derived from an
     * earlier audit event, which may be NON_CRITICAL and lost) because the purge
     * audit event's `previously_archived_by_principal_id` must have a durable source.
     */
    public function up(): void
    {
        Schema::create('cms_media_assets', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('disk', 32)->default('public');
            $table->string('stored_filename', 191)->unique();
            $table->string('original_filename', 255);
            $table->string('mime_type', 127);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->string('caption', 511)->nullable();
            $table->char('sha256', 64)->index();
            $table->string('status', 16)->default('ACTIVE');
            $table->foreignId('uploaded_by_principal_id')
                ->constrained('principals')->restrictOnDelete();
            $table->dateTime('archived_at', 6)->nullable();
            $table->foreignId('archived_by_principal_id')->nullable()
                ->constrained('principals')->restrictOnDelete();
            $table->dateTime('purged_at', 6)->nullable();
            $table->unsignedInteger('purge_attempts')->default(0);
            $table->string('last_purge_error', 511)->nullable();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('status');
            $table->index('mime_type');
            $table->index(['uploaded_by_principal_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media_assets');
    }
};
