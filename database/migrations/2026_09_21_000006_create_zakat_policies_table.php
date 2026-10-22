<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CR-001-B (Schema #6) — versioned Zakat policy per Zakat type
     * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
     * Section 20/55). `rate` is a dimensionless ratio (e.g. 1/40), not a
     * monetary amount, so DECIMAL — never a hard-coded default. No row is
     * seeded; no rate is invented by B (IMP-019 owns actual values).
     *
     * CODEX-CR001B-02 remediation: `version` is an explicit, deterministic
     * per-type ordering identity (assigned by
     * App\Services\Zakat\ZakatPolicyVersioningService, never reused) —
     * historical correction is always a new row/version, never an UPDATE
     * of an existing one. `unique(['zakat_type_id', 'effective_from'])`
     * (upgraded from a plain index) is the DB-level backstop against a
     * same-effective-date conflict for one type; the broader overlapping-
     * range check is enforced by the versioning service under a
     * transaction + lock, since MySQL has no native date-range exclusion
     * constraint.
     */
    public function up(): void
    {
        Schema::create('zakat_policies', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('zakat_type_id')->constrained('zakat_types')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->decimal('rate', 9, 6);
            $table->string('nisab_basis', 64);
            $table->string('source_ref', 255)->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status', 24);

            $table->foreignId('created_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();
            $table->foreignId('updated_by_principal_id')->nullable()->constrained('principals')->restrictOnDelete();

            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->unique(['zakat_type_id', 'version']);
            $table->unique(['zakat_type_id', 'effective_from']);
        });

        $this->addEffectiveRangeCheck();
    }

    public function down(): void
    {
        Schema::dropIfExists('zakat_policies');
    }

    /**
     * CODEX-CR001B-02 remediation (Round 2): a portable, best-effort MySQL
     * 8.x CHECK constraint backstopping the application-level
     * `effective_until >= effective_from` validation
     * (App\Models\Zakat\ZakatPolicy::booted()'s `saving` guard), so the
     * rule is not "merely an optional service validation" bypassable by
     * ordinary application persistence. MySQL 8.0.16+ enforces CHECK
     * without requiring the elevated privilege triggers need — unlike
     * §CODEX-CR001B-01's trigger, this is expected to install on ordinary
     * shared-hosting-grade MySQL 8.x. Still wrapped defensively: if a
     * given host somehow rejects it, the application-level guard remains
     * fully enforced on its own, and this migration does not fail.
     * SQLite has no `ALTER TABLE ... ADD CONSTRAINT` equivalent, so on
     * SQLite the application-level guard is the only layer — disclosed,
     * not hidden.
     */
    private function addEffectiveRangeCheck(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement(
                'ALTER TABLE zakat_policies ADD CONSTRAINT chk_zakat_policies_effective_range '.
                'CHECK (effective_until IS NULL OR effective_until >= effective_from)'
            );
        } catch (Throwable $e) {
            Log::warning('zakat_policies effective-range CHECK constraint could not be installed; application-layer guard remains active.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
};
