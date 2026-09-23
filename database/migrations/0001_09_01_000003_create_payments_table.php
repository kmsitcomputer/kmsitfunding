<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-009 — Payment (Attempt) aggregate
     * (docs/implementation/IMP-009-payment-hub.md "Domain Model" /
     * "Database Impact", HD-IMP009-01 FINAL / LOCKED).
     *
     * One row = one concrete provider transaction/session against a
     * PENDING Donation. donation_id is a plain FK (no uniqueness) — a
     * Donation may have MANY Payments over its lifetime, but at most ONE
     * may be ACTIVE (PENDING/REQUIRES_ACTION) at any moment, enforced at
     * the DB level via the active_slot generated column +
     * UNIQUE(donation_id, active_slot): NULL-distinct unique-index
     * semantics let any number of terminal (active_slot NULL) rows
     * coexist per donation_id while at most one row with
     * active_slot = 1 may exist per donation_id. MySQL 8 and SQLite
     * (3.31+) both carry generated columns, so the invariant is enforced
     * identically in both test environments.
     *
     * expires_at is the Payment's own expiry window (provider-supplied
     * or fallback); expired_at is the terminal-transition timestamp —
     * two distinct columns per the specification. Cross-table amount
     * equality (payments.amount_minor/currency ==
     * donations.amount_minor/currency) is enforced at the service layer
     * at INSERT time — not portably expressible as a CHECK — mirroring
     * donations' own SQLite-exception precedent.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();

            $table->foreignId('donation_id')->constrained('donations')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_reference', 191)->nullable();
            $table->string('channel', 64)->nullable();

            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);

            $table->string('status', 16)->default('PENDING');
            $table->string('idempotency_key', 128)->unique();
            $table->json('instructions_payload')->nullable();
            $table->dateTime('expires_at', 6)->nullable();

            $table->dateTime('succeeded_at', 6)->nullable();
            $table->dateTime('failed_at', 6)->nullable();
            $table->dateTime('expired_at', 6)->nullable();
            $table->dateTime('cancelled_at', 6)->nullable();

            $table->foreignId('verified_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();
            $table->foreignId('cancelled_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();
            $table->string('failure_reason', 255)->nullable();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('donation_id');
            $table->index('provider');
            $table->index('status');
            $table->index('provider_reference');
            $table->index('expires_at');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE payments ADD COLUMN active_slot TINYINT GENERATED ALWAYS AS (
                CASE WHEN status IN ('PENDING', 'REQUIRES_ACTION') THEN 1 ELSE NULL END
            ) STORED
            SQL);

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['donation_id', 'active_slot'], 'ux_payments_donation_active_slot');
            $table->unique(['provider', 'provider_reference'], 'ux_payments_provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
