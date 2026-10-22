<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CR-001-B (Schema #11) — immutable Fidyah calculation record, mirroring
     * zakat_calculation_snapshots exactly (docs/change-requests/
     * CR-001-public-experience-cms-ziswaf-admin-v2.md Section 20/55).
     * No `updated_at` column — never updated after insert.
     *
     * CODEX-CR001B-01 remediation: BEFORE UPDATE / BEFORE DELETE triggers
     * mirror zakat_calculation_snapshots' database-layer backstop exactly.
     */
    public function up(): void
    {
        Schema::create('fidyah_calculation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('fidyah_policy_id')->constrained('fidyah_policies')->restrictOnDelete();
            $table->json('input');
            $table->json('result');
            $table->dateTime('computed_at', 6);
            $table->foreignId('acting_principal_id')->nullable()->constrained('principals')->restrictOnDelete();

            $table->dateTime('created_at', 6);

            $table->index('fidyah_policy_id');
        });

        $this->createAppendOnlyTriggers();
    }

    public function down(): void
    {
        $this->dropAppendOnlyTriggers();

        Schema::dropIfExists('fidyah_calculation_snapshots');
    }

    private function createAppendOnlyTriggers(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->tryUnprepared('
                CREATE TRIGGER trg_fidyah_calc_snapshots_no_update
                BEFORE UPDATE ON fidyah_calculation_snapshots
                FOR EACH ROW
                SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'fidyah_calculation_snapshots rows are append-only; UPDATE is not permitted.\'
            ');
            $this->tryUnprepared('
                CREATE TRIGGER trg_fidyah_calc_snapshots_no_delete
                BEFORE DELETE ON fidyah_calculation_snapshots
                FOR EACH ROW
                SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'fidyah_calculation_snapshots rows are append-only; DELETE is not permitted.\'
            ');
        } else {
            $this->tryUnprepared('
                CREATE TRIGGER trg_fidyah_calc_snapshots_no_update
                BEFORE UPDATE ON fidyah_calculation_snapshots
                BEGIN
                    SELECT RAISE(ABORT, \'fidyah_calculation_snapshots rows are append-only; UPDATE is not permitted.\');
                END
            ');
            $this->tryUnprepared('
                CREATE TRIGGER trg_fidyah_calc_snapshots_no_delete
                BEFORE DELETE ON fidyah_calculation_snapshots
                BEGIN
                    SELECT RAISE(ABORT, \'fidyah_calculation_snapshots rows are append-only; DELETE is not permitted.\');
                END
            ');
        }
    }

    private function dropAppendOnlyTriggers(): void
    {
        $this->tryUnprepared('DROP TRIGGER IF EXISTS trg_fidyah_calc_snapshots_no_update');
        $this->tryUnprepared('DROP TRIGGER IF EXISTS trg_fidyah_calc_snapshots_no_delete');
    }

    private function tryUnprepared(string $sql): void
    {
        try {
            DB::unprepared($sql);
        } catch (Throwable $e) {
            Log::warning('fidyah_calculation_snapshots append-only trigger could not be installed; application-layer guard remains active.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
};
