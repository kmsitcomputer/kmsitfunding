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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('intended_email');
            $table->string('token_hash');
            $table->string('invited_actor');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('issuer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoker_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('intended_email');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
