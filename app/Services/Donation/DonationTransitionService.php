<?php

namespace App\Services\Donation;

use App\Models\Donation\Donation;
use App\Models\Rbac\Principal;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-008 — the ONLY writer of Donation lifecycle status
 * (docs/implementation/IMP-008-donation.md "State / Lifecycle" / BR-7,
 * mirroring CampaignLifecycleService's exact concurrency idiom).
 * Every transition locks the Donation row first and re-verifies its
 * current status under that lock — two concurrent attempts on the same
 * Donation MUST NOT both succeed; the loser observes the terminal state
 * and is rejected with a typed conflict.
 *
 * WHO may call what is a Policy-layer concern (DonationPolicy), never
 * decided here: markSucceeded/markFailed/markExpired are reserved for an
 * authorized System Principal invocation (IMP-009/011 define the caller);
 * cancel is the donor/admin PENDING-path transition. A terminal Donation
 * is never mutated back (BR-7).
 */
class DonationTransitionService
{
    public function __construct(private readonly DonationAuditLogger $auditLogger) {}

    public function markSucceeded(Donation $donation, Principal $systemActor): Donation
    {
        return DB::transaction(function () use ($donation, $systemActor) {
            $locked = $this->lockedPending($donation);

            $locked->forceFill([
                'status' => 'SUCCEEDED',
                'succeeded_at' => now(),
            ])->save();

            $this->auditLogger->recordDonationSucceeded($locked->id, [
                'donation_ulid' => $locked->ulid,
            ], $systemActor);

            return $locked;
        });
    }

    public function markFailed(Donation $donation, Principal $systemActor): Donation
    {
        return DB::transaction(function () use ($donation, $systemActor) {
            $locked = $this->lockedPending($donation);

            $locked->forceFill([
                'status' => 'FAILED',
                'failed_at' => now(),
            ])->save();

            $this->auditLogger->recordDonationFailed($locked->id, [
                'donation_ulid' => $locked->ulid,
            ], $systemActor);

            return $locked;
        });
    }

    public function markExpired(Donation $donation, Principal $systemActor): Donation
    {
        return DB::transaction(function () use ($donation, $systemActor) {
            $locked = $this->lockedPending($donation);

            $locked->forceFill([
                'status' => 'EXPIRED',
                'expired_at' => now(),
            ])->save();

            $this->auditLogger->recordDonationExpired($locked->id, [
                'donation_ulid' => $locked->ulid,
            ], $systemActor);

            return $locked;
        });
    }

    public function cancel(Donation $donation, Principal $actor): Donation
    {
        return DB::transaction(function () use ($donation, $actor) {
            $locked = $this->lockedPending($donation);

            $locked->forceFill([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
                'cancelled_by_principal_id' => $actor->id,
            ])->save();

            $this->auditLogger->recordDonationCancelled($locked->id, [
                'donation_ulid' => $locked->ulid,
                'cancelled_by_principal_id' => $actor->id,
            ], $actor);

            return $locked;
        });
    }

    private function lockedPending(Donation $donation): Donation
    {
        $locked = Donation::query()->whereKey($donation->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== 'PENDING') {
            throw new DonationTransitionConflictException(
                'invalid_transition',
                "Donation {$locked->id} is status={$locked->status}; only PENDING may transition."
            );
        }

        return $locked;
    }
}
