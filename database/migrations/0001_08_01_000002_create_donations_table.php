<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMP-008 — Donation (docs/implementation/IMP-008-donation.md
     * "Database Impact", HD-IMP008-01A/HD-IMP008-04/HD-IMP008-05A).
     * Campaign-only: campaign_id NOT NULL, no fund_id/program_id column.
     * donor_principal_id NULL means guest (BR-2 XOR guard below).
     * idempotency_key UNIQUE is the deterministic backstop (BR-12).
     *
     * The BR-2 CHECK is enforced at the DB level on MySQL. SQLite cannot
     * add a CHECK to an already-created table via ALTER TABLE — hence the
     * CREATE-TABLE-time portable API is absent from the schema builder, so
     * the SAME invariant is ALSO enforced at the application layer (see
     * App\Models\Donation\Donation's `saving` guard), which is required by
     * the specification anyway — mirroring `principals`' own
     * migration/app-guard pattern exactly.
     */
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();

            $table->foreignId('campaign_id')->constrained('campaigns')->restrictOnDelete();
            $table->foreignId('donor_principal_id')->nullable()->constrained('principals')->restrictOnDelete();
            $table->foreignId('recurring_occurrence_id')->nullable()->constrained('donation_recurring_occurrences')->restrictOnDelete();

            $table->string('guest_name', 150)->nullable();
            $table->string('guest_email', 255)->nullable();
            $table->string('donor_display_name', 150)->nullable();

            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);

            $table->string('status', 16)->default('PENDING');
            $table->boolean('is_anonymous')->default(false);

            $table->string('idempotency_key', 128)->unique();

            $table->dateTime('succeeded_at', 6)->nullable();
            $table->dateTime('failed_at', 6)->nullable();
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->dateTime('expired_at', 6)->nullable();
            $table->foreignId('cancelled_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('campaign_id');
            $table->index('donor_principal_id');
            $table->index('status');
            $table->index('recurring_occurrence_id');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE donations ADD CONSTRAINT chk_donations_donor_path CHECK (
                    (donor_principal_id IS NOT NULL)
                    OR
                    (guest_name IS NOT NULL AND guest_email IS NOT NULL)
                )
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
