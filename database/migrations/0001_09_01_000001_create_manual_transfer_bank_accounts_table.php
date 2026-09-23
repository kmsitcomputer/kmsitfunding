<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-009 — Manual Bank Transfer destination-account catalog
     * (docs/implementation/IMP-009-payment-hub.md "Domain Model" /
     * "Database Impact"). Display/routing configuration only — never a
     * balance, never a Ledger account, holds no secret (account
     * number/holder name is not a credential).
     */
    public function up(): void
    {
        Schema::create('manual_transfer_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('bank_name', 100);
            $table->string('account_number', 64);
            $table->string('account_holder_name', 150);
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('currency');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_transfer_bank_accounts');
    }
};
