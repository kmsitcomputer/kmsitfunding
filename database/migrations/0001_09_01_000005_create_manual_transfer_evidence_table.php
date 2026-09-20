<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-009 — Manual Bank Transfer proof-of-transfer evidence
     * (docs/implementation/IMP-009-payment-hub.md "Domain Model" /
     * "Database Impact", HD-IMP009-06/07/08 FINAL / LOCKED). Append-only:
     * a resubmission is always a NEW row, never an UPDATE. file_path/
     * mime_type/size_bytes/declared_* are set at submission and never
     * mutated; reviewed_* are set exactly once at review time.
     * review_outcome's third value AMOUNT_MISMATCH_HOLD routes a
     * mismatched transfer to controlled exception/manual review
     * (HD-IMP009-07) — never an automatic APPROVED/REJECTED.
     */
    public function up(): void
    {
        Schema::create('manual_transfer_evidence', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();

            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('submitted_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();

            $table->string('file_path', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');

            $table->unsignedBigInteger('declared_amount_minor')->nullable();
            $table->char('declared_currency', 3)->nullable();
            $table->dateTime('declared_transferred_at', 6)->nullable();

            $table->foreignId('reviewed_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();
            $table->dateTime('reviewed_at', 6)->nullable();
            $table->string('review_outcome', 24)->nullable();
            $table->string('review_notes', 1000)->nullable();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('payment_id');
            $table->index('reviewed_by_principal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_transfer_evidence');
    }
};
