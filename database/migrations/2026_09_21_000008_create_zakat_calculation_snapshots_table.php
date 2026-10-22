<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CR-001-B (Schema #9) — immutable Zakat calculation record
     * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
     * Section 20/55). Append-only: `input`/`result` are JSON snapshots of
     * the ValueObjects at computation time, never mutated after insert.
     * `acting_principal_id` is nullable — the calculator is guest-usable
     * (Section 22 boundary: it never creates a Payment Attempt directly).
     * No `updated_at` column — this row is never updated (see
     * ZakatCalculationSnapshot::UPDATED_AT = null).
     *
     * CODEX-CR001B-03 remediation: `nisab_policy_id` / `gold_price_reference_id`
     * are typed, nullable (a Zakat type that does not price against gold
     * never requires one), RESTRICT-on-delete provenance references — the
     * exact governing NisabPolicy/GoldPriceReference version used for a
     * historical calculation must remain identifiable, never only implied
     * by arbitrary JSON.
     *
     * CODEX-CR001B-01 remediation: a BEFORE UPDATE / BEFORE DELETE
     * trigger pair is the database-layer backstop against any write that
     * reaches this table outside Eloquent (raw SQL, another process, a
     * future migration bug) — see App\Models\Zakat\ZakatCalculationSnapshot
     * for the matching application-layer guards. INSERT is untouched.
     *
     * Trigger installation is best-effort and non-fatal: some MySQL
     * hosts (observed on the disposable kmsitdonation_imp003_test
     * server — MySQL error 1419) refuse CREATE TRIGGER for a
     * non-SUPER/SYSTEM_VARIABLES_ADMIN user when binary logging is
     * enabled and `log_bin_trust_function_creators` is off — exactly
     * the kind of restricted privilege a shared-hosting account may
     * have (Section "Shared-hosting-compatible production remains
     * mandatory"). If trigger creation fails, it is logged and skipped
     * rather than failing the whole migration; the application-layer
     * guard (save()/delete() overrides + AppendOnlyBuilder) remains the
     * active enforcement on such a host. Where the privilege exists,
     * the trigger installs and provides real defense-in-depth.
     */
    public function up(): void
    {
        Schema::create('zakat_calculation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('zakat_type_id')->constrained('zakat_types')->restrictOnDelete();
            $table->foreignId('zakat_policy_id')->constrained('zakat_policies')->restrictOnDelete();
            $table->foreignId('nisab_policy_id')->nullable()->constrained('nisab_policies')->restrictOnDelete();
            $table->foreignId('gold_price_reference_id')->nullable()->constrained('gold_price_references')->restrictOnDelete();
            $table->json('input');
            $table->json('result');
            $table->dateTime('computed_at', 6);
            $table->foreignId('acting_principal_id')->nullable()->constrained('principals')->restrictOnDelete();

            $table->dateTime('created_at', 6);

            $table->index('zakat_policy_id');
        });

        $this->createAppendOnlyTriggers();
    }

    public function down(): void
    {
        $this->dropAppendOnlyTriggers();

        Schema::dropIfExists('zakat_calculation_snapshots');
    }

    private function createAppendOnlyTriggers(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->tryUnprepared('
                CREATE TRIGGER trg_zakat_calc_snapshots_no_update
                BEFORE UPDATE ON zakat_calculation_snapshots
                FOR EACH ROW
                SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'zakat_calculation_snapshots rows are append-only; UPDATE is not permitted.\'
            ');
            $this->tryUnprepared('
                CREATE TRIGGER trg_zakat_calc_snapshots_no_delete
                BEFORE DELETE ON zakat_calculation_snapshots
                FOR EACH ROW
                SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'zakat_calculation_snapshots rows are append-only; DELETE is not permitted.\'
            ');
        } else {
            $this->tryUnprepared('
                CREATE TRIGGER trg_zakat_calc_snapshots_no_update
                BEFORE UPDATE ON zakat_calculation_snapshots
                BEGIN
                    SELECT RAISE(ABORT, \'zakat_calculation_snapshots rows are append-only; UPDATE is not permitted.\');
                END
            ');
            $this->tryUnprepared('
                CREATE TRIGGER trg_zakat_calc_snapshots_no_delete
                BEFORE DELETE ON zakat_calculation_snapshots
                BEGIN
                    SELECT RAISE(ABORT, \'zakat_calculation_snapshots rows are append-only; DELETE is not permitted.\');
                END
            ');
        }
    }

    private function dropAppendOnlyTriggers(): void
    {
        $this->tryUnprepared('DROP TRIGGER IF EXISTS trg_zakat_calc_snapshots_no_update');
        $this->tryUnprepared('DROP TRIGGER IF EXISTS trg_zakat_calc_snapshots_no_delete');
    }

    private function tryUnprepared(string $sql): void
    {
        try {
            DB::unprepared($sql);
        } catch (Throwable $e) {
            Log::warning('zakat_calculation_snapshots append-only trigger could not be installed; application-layer guard remains active.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
};
