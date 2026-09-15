<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-005 — managed page identity + lifecycle (docs/implementation/IMP-005-cms.md
     * section 13 "cms_pages"). No path column, no homepage column: both live in their
     * single-purpose stores (cms_paths, cms_homepage_assignment). The three revision
     * pointer columns are created here as plain nullable unsigned bigints; their
     * composite ownership FKs are added in a later migration once cms_content_revisions
     * exists (section 13 "DDL ORDERING CONSEQUENCE" — the two tables reference each
     * other, so pages/articles must exist first, without the pointer FKs).
     */
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('title', 255);
            $table->string('status', 16);

            $table->unsignedBigInteger('latest_draft_revision_id')->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->unsignedBigInteger('scheduled_revision_id')->nullable();

            $table->dateTime('publish_at', 6)->nullable();
            $table->dateTime('unpublish_at', 6)->nullable();
            $table->unsignedBigInteger('schedule_version')->default(0);
            $table->foreignId('scheduled_by_principal_id')->nullable()
                ->constrained('principals')->restrictOnDelete();
            $table->dateTime('scheduled_at', 6)->nullable();

            $table->foreignId('created_by_principal_id')
                ->constrained('principals')->restrictOnDelete();
            $table->foreignId('updated_by_principal_id')
                ->constrained('principals')->restrictOnDelete();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('status');
            $table->index('updated_at');
            $table->index(['publish_at', 'id']);
            $table->index(['unpublish_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
