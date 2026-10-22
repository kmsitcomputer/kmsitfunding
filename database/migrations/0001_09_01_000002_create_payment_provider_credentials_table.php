<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-009 — provider secret configuration
     * (docs/implementation/IMP-009-payment-hub.md "Domain Model" /
     * "Database Impact" / "Configuration"). encrypted_secret is
     * application-layer encrypted (Laravel's encrypter, APP_KEY-backed) —
     * no plaintext secret column, ever. Never serialized through any read
     * API/admin response (masked/omitted unconditionally).
     */
    public function up(): void
    {
        Schema::create('payment_provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->unique();
            $table->string('mode', 16);
            $table->text('encrypted_secret');
            $table->boolean('is_enabled')->default(false);

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_provider_credentials');
    }
};
