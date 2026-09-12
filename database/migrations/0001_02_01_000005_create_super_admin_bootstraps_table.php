<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Durable one-time guard (Q25). A single row with lock_key = 'first_super_admin'
        // is inserted the moment the bootstrap command completes; the unique constraint
        // on lock_key is what makes a repeat bootstrap attempt fail deterministically,
        // even under a concurrent double-invocation. No credential is stored here.
        Schema::create('super_admin_bootstraps', function (Blueprint $table) {
            $table->id();
            $table->string('lock_key')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('bootstrapped_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_bootstraps');
    }
};
