<?php

namespace App\Console\Commands;

use App\Models\Donation\DonationRecurringPlan;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Donation\RecurringPlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * IMP-008 — monthly recurring occurrence generation driver
 * (docs/implementation/IMP-008-donation.md "Domain Model", HD-IMP008-02/
 * HD-IMP008-03). For every ACTIVE plan whose next_occurrence_at is due,
 * delegates the actual decision to RecurringPlanService::
 * generateOccurrence() — this command never mutates plan/occurrence
 * state itself and never emits an audit event itself (thin per-plan
 * driver, mirroring the CMS scheduler). A plan whose next monthly
 * occurrence fails generation is left for the next run (transient);
 * persistent failures are recorded via markOccurrenceFailed() — never
 * auto-retried (BR-11).
 *
 * Shared-hosting compatible (Laravel Scheduler + Cron). Monthly cadence:
 * hourly polling with a due-timestamp hint (select due <= cutoff, not
 * equality, so missed cron runs are caught); generation itself remains
 * monthly per plan (next_occurrence_at advances one month per success).
 */
class GenerateRecurringOccurrences extends Command
{
    protected $signature = 'donation:generate-occurrences';

    protected $description = 'Generate due monthly recurring donation occurrences (IMP-008 HD-IMP008-02/03).';

    public function handle(RecurringPlanService $plans): int
    {
        $systemActor = $this->resolveSchedulerPrincipal();

        if ($systemActor === null) {
            $this->error('donation.scheduler System Principal is not seeded — run DonationSystemPrincipalSeeder first.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now();

        $dueIds = DonationRecurringPlan::query()
            ->where('status', 'ACTIVE')
            ->where('frequency', 'MONTHLY')
            ->whereNotNull('next_occurrence_at')
            ->where('next_occurrence_at', '<=', $cutoff)
            ->pluck('id');

        $generated = 0;

        foreach ($dueIds as $id) {
            $plan = DonationRecurringPlan::query()->whereKey($id)->first();

            if ($plan === null) {
                continue;
            }

            try {
                $plans->generateOccurrence($plan, ['scheduled_at' => $cutoff], $systemActor);
                $generated++;
            } catch (\Throwable $e) {
                logger()->error('Recurring occurrence generation failed for one plan; will retry next run.', [
                    'plan_id' => $id,
                    'exception' => $e->getMessage(),
                ]);
                $this->error("Failed to generate occurrence for Plan #{$id}: ".$e->getMessage());
            }
        }

        $this->info("Generated {$generated} recurring occurrence(s) at cutoff {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }

    private function resolveSchedulerPrincipal(): ?Principal
    {
        $systemPrincipal = SystemPrincipal::where('code', 'donation.scheduler')->first();

        if ($systemPrincipal === null) {
            return null;
        }

        return Principal::where('system_principal_id', $systemPrincipal->id)->first();
    }
}
