<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-007 — Campaign-owned uploaded files, mirroring theme_assets'
     * shape exactly (docs/implementation/IMP-007-campaign-program-fund.md
     * section 8). CASCADE on campaign_id: an asset has no life independent
     * of its owning Campaign.
     */
    public function up(): void
    {
        Schema::create('campaign_media_assets', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
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
        Schema::dropIfExists('campaign_media_assets');
    }
};
