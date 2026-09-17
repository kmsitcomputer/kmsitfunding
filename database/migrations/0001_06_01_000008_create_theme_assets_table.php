<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-006 — theme-owned uploaded files (docs/implementation/
     * IMP-006-theme-engine.md section 17), administratively separate from
     * `cms_media_assets` (IMP-005 §7: CMS media rows are content-use only).
     * `stored_filename` is server-generated (ULID), never user input.
     */
    public function up(): void
    {
        Schema::create('theme_assets', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('stored_filename', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('sha256', 64);
            $table->string('status', 16)->default('ACTIVE');

            $table->foreignId('uploaded_by_principal_id')->constrained('principals')->restrictOnDelete();
            $table->foreignId('archived_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();
            $table->dateTime('archived_at', 6)->nullable();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('status');
            $table->index('sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_assets');
    }
};
