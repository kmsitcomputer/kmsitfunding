<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CR-001-B (Schema #10) — versioned Fidyah rate-per-missed-fast-day
     * policy (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
     * Section 20/55). `rate_amount_minor` is a monetary amount (per day),
     * so integer minor units per Section 64 — never FLOAT/DOUBLE, never a
     * hard-coded default. No row is seeded; no rate is invented by B.
     *
     * CODEX-CR001B-02 remediation: `version` is an explicit, deterministic
     * org-wide ordering identity (assigned by
     * App\Services\Fidyah\FidyahPolicyVersioningService), mirroring
     * zakat_policies exactly. `unique('effective_from')` is the DB-level
     * backstop against a same-effective-date conflict.
     */
    public function up(): void
    {
        Schema::create('fidyah_policies', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->unsignedInteger('version')->unique();
            $table->unsignedBigInteger('rate_amount_minor');
            $table->char('currency', 3);
            $table->string('source_ref', 255)->nullable();
            $table->date('effective_from')->unique();
            $table->date('effective_until')->nullable();
            $table->string('status', 24);

            $table->foreignId('created_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();
            $table->foreignId('updated_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });

        $this->addEffectiveRangeCheck();
    }

    public function down(): void
    {
        Schema::dropIfExists('fidyah_policies');
    }

    /**
     * CODEX-CR001B-02 remediation (Round 2): mirrors zakat_policies'
     * best-effort MySQL 8.x CHECK constraint exactly.
     */
    private function addEffectiveRangeCheck(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement(
                'ALTER TABLE fidyah_policies ADD CONSTRAINT chk_fidyah_policies_effective_range '.
                'CHECK (effective_until IS NULL OR effective_until >= effective_from)'
            );
        } catch (Throwable $e) {
            Log::warning('fidyah_policies effective-range CHECK constraint could not be installed; application-layer guard remains active.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
};
