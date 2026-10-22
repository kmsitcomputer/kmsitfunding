<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-008 — Recurring Occurrence (docs/implementation/IMP-008-donation.md
     * "Database Impact", HD-IMP008-03). donation_id is created here as a
     * plain NULLABLE column WITHOUT its FK (a forward reference to the
     * not-yet-created donations table); the FK and unique constraint are
     * added by 0001_08_01_000003 after donations exists, breaking the
     * circular dependency — do not reorder without re-verifying the FK
     * cycle. FAILED is terminal; no automatic retry (BR-11).
     */
    public function up(): void
    {
        Schema::create('donation_recurring_occurrences', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();

            $table->foreignId('recurring_plan_id')->constrained('donation_recurring_plans')->restrictOnDelete();

            $table->dateTime('scheduled_at', 6);
            $table->unsignedBigInteger('donation_id')->nullable();
            $table->string('status', 16)->default('SCHEDULED');
            $table->dateTime('generated_at', 6)->nullable();
            $table->text('failure_reason')->nullable();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('recurring_plan_id');
            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_recurring_occurrences');
    }
};
