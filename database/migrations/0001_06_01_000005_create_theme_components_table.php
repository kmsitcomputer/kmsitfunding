<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-006 — Component/Block instances (docs/implementation/
     * IMP-006-theme-engine.md section 11). `type` is a closed set enforced
     * at the application layer (ComponentType enum); `config` is a JSON
     * payload validated server-side against that type's own schema BEFORE
     * every save (section 21 "Configuration tampering") — never persisted
     * unvalidated.
     */
    public function up(): void
    {
        Schema::create('theme_components', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('theme_section_id')->constrained('theme_sections')->cascadeOnDelete();
            $table->string('type', 32);
            $table->json('config');
            $table->unsignedInteger('position');

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->unique(['theme_section_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_components');
    }
};
