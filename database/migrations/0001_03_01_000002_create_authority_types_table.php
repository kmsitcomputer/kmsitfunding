<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-003 — extensible Business/Financial Authority Type registry (see
     * docs/05-rbac/BUSINESS-AUTHORITY-MODEL.md). IMP-003 seeds only the 7
     * approved types; later domain stages register their own via their own
     * migration, per "Business Authority > Authority Type Registry".
     */
    public function up(): void
    {
        Schema::create('authority_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_financial')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authority_types');
    }
};
