<?php

namespace App\Console\Commands;

use App\Models\Donation\Donation;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Donation\DonationTransitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * IMP-008 — Donation expiration sweep (docs/implementation/
 * IMP-008-donation.md "State / Lifecycle", HD-IMP008-04). System-
 * initiated PENDING -> EXPIRED for donations unresolved within the
 * CONFIGURABLE window. Config contract: config(
 * 'donation.pending_expiry_minutes'), default NULL — while unset, the
 * sweep MUST NOT act and no Donation ever transitions to EXPIRED. No
 * numeric default is asserted here (inventing one is prohibited).
 *
 * Shared-hosting compatible (Laravel Scheduler + Cron, no Redis/
 * Supervisor/PM2). Each row transitions through
 * DonationTransitionService::markExpired() (own lock + state re-check),
 * so overlapping runs are safe by construction; withoutOverlapping()
 * is a courtesy only. A single identity's failure never aborts the
 * whole run — mirroring the CMS scheduler precedent.
 */
class ExpirePendingDonations extends Command
{
    protected $signature = 'donation:expire-pending';

    protected $description = 'Expire PENDING donations unresolved within the configured window (IMP-008 HD-IMP008-04).';

    public function handle(DonationTransitionService $transitions): int
    {
        $expiryMinutes = config('donation.pending_expiry_minutes');

        if ($expiryMinutes === null) {
            $this->info('Donation expiration is not configured (donation.pending_expiry_minutes is null) — nothing to expire.');

            return self::SUCCESS;
        }

        $systemActor = $this->resolveSchedulerPrincipal();

        if ($systemActor === null) {
            $this->error('donation.scheduler System Principal is not seeded — run DonationSystemPrincipalSeeder first.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subMinutes((int) $expiryMinutes);

        $dueIds = Donation::query()
            ->where('status', 'PENDING')
            ->where('created_at', '<=', $cutoff)
            ->pluck('id');

        $expired = 0;

        foreach ($dueIds as $id) {
            $donation = Donation::query()->whereKey($id)->first();

            if ($donation === null) {
                continue;
            }

            try {
                $transitions->markExpired($donation, $systemActor);
                $expired++;
            } catch (\Throwable $e) {
                logger()->error('Donation expiration failed for one donation; will retry next run.', [
                    'donation_id' => $id,
                    'exception' => $e->getMessage(),
                ]);
                $this->error("Failed to expire Donation #{$id}: ".$e->getMessage());
            }
        }

        $this->info("Expired {$expired} pending donation(s) older than {$cutoff->toDateTimeString()}.");

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
