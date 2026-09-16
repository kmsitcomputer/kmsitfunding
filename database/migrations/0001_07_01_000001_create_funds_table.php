<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-007 — Fund: the designation/restriction context for money
     * (docs/implementation/IMP-007-campaign-program-fund.md section 8). NOT
     * a payment transaction, ledger account, mutable balance, or wallet — no
     * amount column exists on this table. No default Fund is ever seeded
     * (HD-IMP007-04).
     */
    public function up(): void
    {
        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->text('restriction_note')->nullable();
            $table->string('status', 16)->default('ACTIVE');

            $table->foreignId('created_by_principal_id')->constrained('principals')->restrictOnDelete();
            $table->foreignId('updated_by_principal_id')->constrained('principals')->restrictOnDelete();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funds');
    }
};
