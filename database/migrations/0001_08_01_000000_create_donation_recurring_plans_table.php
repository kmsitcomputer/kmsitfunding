<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-008 — Recurring Plan (docs/implementation/IMP-008-donation.md
     * "Database Impact", HD-IMP008-01B/HD-IMP008-02/HD-IMP008-03).
     * Authenticated-donor-only (donor_principal_id NOT NULL); frequency
     * validated against config('donation.recurring_frequencies') at the
     * service layer, never as a hard-coded code path (BR-9).
     */
    public function up(): void
    {
        Schema::create('donation_recurring_plans', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();

            $table->foreignId('donor_principal_id')->constrained('principals')->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained('campaigns')->restrictOnDelete();

            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);

            $table->string('frequency', 16);
            $table->string('status', 16)->default('ACTIVE');
            $table->boolean('is_anonymous')->default(false);

            $table->dateTime('starts_at', 6);
            $table->dateTime('ends_at', 6)->nullable();
            $table->dateTime('next_occurrence_at', 6)->nullable();

            $table->dateTime('paused_at', 6)->nullable();
            $table->foreignId('paused_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->foreignId('cancelled_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('donor_principal_id');
            $table->index('campaign_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_recurring_plans');
    }
};
