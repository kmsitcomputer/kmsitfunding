<?php

namespace App\Console\Commands;

use App\Models\Payment\Payment;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Payment\Exceptions\PaymentTransitionConflictException;
use App\Services\Payment\PaymentTransitionService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * IMP-009 — Payment expiration sweep (docs/implementation/
 * IMP-009-payment-hub.md "Expiration", HD-IMP009-09 FINAL / LOCKED).
 * System-initiated PENDING/REQUIRES_ACTION -> EXPIRED for payments
 * unresolved within their own window: provider-supplied expires_at
 * when present, else the CONFIGURABLE fallback
 * (config('payment.pending_expiry_minutes')) measured from created_at
 * for payments without a provider-supplied expiry. While the fallback
 * is unset, no such Payment is ever swept (mirroring HD-IMP008-04).
 * The owning Donation is UNAFFECTED (two independent clocks).
 *
 * Shared-hosting compatible (Laravel Scheduler + Cron, no Redis/
 * Supervisor/PM2). Each row transitions through
 * PaymentTransitionService::markExpired() (own lock + state
 * re-check), so overlapping runs are safe by construction;
 * withoutOverlapping() is a courtesy only.
 */
class ExpirePendingPayments extends Command
{
    protected $signature = 'payment:expire-pending';

    protected $description = 'Expire pending payments unresolved within their own window (IMP-009 HD-IMP009-09).';

    public function handle(PaymentTransitionService $transitions, PrincipalService $principals): int
    {
        $fallbackMinutes = config('payment.pending_expiry_minutes');

        $catalog = SystemPrincipal::where('code', 'scheduler.payment-expiry-sweep')->first();

        if ($catalog === null) {
            $this->error('scheduler.payment-expiry-sweep System Principal is not seeded — run PaymentSystemPrincipalSeeder first.');

            return self::FAILURE;
        }

        $systemActor = $principals->forSystem($catalog);

        $expired = 0;

        $dueIds = Payment::query()
            ->whereIn('status', ['PENDING', 'REQUIRES_ACTION'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->pluck('id');

        foreach ($dueIds as $id) {
            $this->expireOne((int) $id, $transitions, $systemActor, $expired);
        }

        if ($fallbackMinutes !== null) {
            $cutoff = Carbon::now('UTC')->subMinutes((int) $fallbackMinutes);

            $fallbackIds = Payment::query()
                ->whereIn('status', ['PENDING', 'REQUIRES_ACTION'])
                ->whereNull('expires_at')
                ->where('created_at', '<=', $cutoff)
                ->pluck('id');

            foreach ($fallbackIds as $id) {
                $this->expireOne((int) $id, $transitions, $systemActor, $expired);
            }
        }

        $this->info("Expired {$expired} pending payment(s).");

        return self::SUCCESS;
    }

    private function expireOne(int $id, PaymentTransitionService $transitions, Principal $systemActor, int &$expired): void
    {
        $payment = Payment::query()->whereKey($id)->first();

        if ($payment === null) {
            return;
        }

        try {
            // F-13: the sweep acquires the SAME fixed Donation-then-
            // Payment lock order as every other multi-aggregate Payment
            // operation (webhook processing, manual verification),
            // so a concurrent webhook and the sweep contend on one
            // global order — never deadlock. markExpired() then
            // re-verifies state under its own nested lock.
            DB::transaction(function () use ($payment, $transitions, $systemActor, &$expired) {
                $payment->donation()->lockForUpdate()->first();
                Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

                $transitions->markExpired($payment, $systemActor);
                $expired++;
            });
        } catch (PaymentTransitionConflictException $e) {
            // Expected lost race: a concurrent webhook/verification won
            // the row and moved it terminal between candidate selection
            // and this commit. Not an error — the row needs no sweep.
            $this->info("Payment #{$id} already resolved ({$e->reason}); skipping.");
        } catch (\Throwable $e) {
            logger()->error('Payment expiration failed for one payment; will retry next run.', [
                'payment_id' => $id,
                'exception' => $e->getMessage(),
            ]);
            $this->error("Failed to expire Payment #{$id}: ".$e->getMessage());
        }
    }
}
