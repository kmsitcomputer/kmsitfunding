<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-003 — canonical Permission catalog, synced from the code-defined
     * Permission Registry (see app/Services/Rbac/PermissionRegistry.php). See
     * docs/implementation/IMP-003-rbac-scope-business-authority.md
     * "Permission Model".
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('module')->nullable();
            $table->timestamp('deprecated_at')->nullable();
            $table->timestamps();

            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
