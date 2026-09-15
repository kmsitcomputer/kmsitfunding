<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-005 — managed article identity + lifecycle (docs/implementation/IMP-005-cms.md
     * section 13 "cms_articles"). Same shape as cms_pages, plus `excerpt` (a current
     * projection of the published revision's excerpt, maintained only by the publish
     * transaction — never free-write) and `first_published_at` (identity-level, set
     * once by the identity's first-ever publication, never rewritten). No is_homepage
     * or path column: Articles are never homepage-designated and paths live in
     * cms_paths. article_type lives on the revision (section 13 "Article
     * classification"), not here.
     */
    public function up(): void
    {
        Schema::create('cms_articles', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('title', 255);
            $table->string('status', 16);
            $table->string('excerpt', 511)->nullable();
            $table->dateTime('first_published_at', 6)->nullable();

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
        Schema::dropIfExists('cms_articles');
    }
};
