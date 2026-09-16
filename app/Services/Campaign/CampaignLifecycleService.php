<?php

namespace App\Services\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\Fund;
use App\Models\Rbac\Principal;
use App\Services\Campaign\Exceptions\CampaignTransitionConflictException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-007 — the ONLY writer of Campaign lifecycle status
 * (docs/implementation/IMP-007-campaign-program-fund.md sections 11/12/23,
 * HD-IMP007-01). Five independent transitions: submit, approve, reject,
 * publish, close. Approve and publish are deliberately SEPARATE methods —
 * never collapsed — each gated by its own permission at the Policy layer.
 * Every transition locks the Campaign row first and re-verifies its current
 * status under that lock (BR-7) — mirrors PublicationService's exact
 * concurrency idiom.
 */
class CampaignLifecycleService
{
    public function __construct(private readonly CampaignAuditLogger $auditLogger) {}

    public function submit(Campaign $campaign, Principal $actor): Campaign
    {
        return DB::transaction(function () use ($campaign, $actor) {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'DRAFT') {
                throw new CampaignTransitionConflictException(
                    'invalid_transition',
                    "Campaign {$locked->id} is status={$locked->status}; only DRAFT may be submitted for review."
                );
            }

            $locked->forceFill([
                'status' => 'REVIEW',
                'submitted_at' => now(),
                'submitted_by_principal_id' => $actor->id,
            ])->save();

            $this->auditLogger->recordCampaignSubmitted($locked->id, $actor);

            return $locked;
        });
    }

    /**
     * REVIEW -> APPROVED (HD-IMP007-01). Does NOT require a Fund — content
     * approval is separable from go-live readiness (BR-1 is enforced at
     * publish() instead).
     */
    public function approve(Campaign $campaign, Principal $actor): Campaign
    {
        return DB::transaction(function () use ($campaign, $actor) {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'REVIEW') {
                throw new CampaignTransitionConflictException(
                    'invalid_transition',
                    "Campaign {$locked->id} is status={$locked->status}; only REVIEW may be approved."
                );
            }

            $locked->forceFill([
                'status' => 'APPROVED',
                'approved_at' => now(),
                'approved_by_principal_id' => $actor->id,
            ])->save();

            $this->auditLogger->recordCampaignApproved($locked->id, $actor);

            return $locked;
        });
    }

    /**
     * REVIEW -> DRAFT. Rejection is only reachable from REVIEW in v1 — there
     * is no "un-approve" transition from APPROVED (HD-IMP007-01 forbids an
     * automatic unapproval policy; no manual one was requested either).
     */
    public function reject(Campaign $campaign, string $reason, Principal $actor): Campaign
    {
        return DB::transaction(function () use ($campaign, $reason, $actor) {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'REVIEW') {
                throw new CampaignTransitionConflictException(
                    'invalid_transition',
                    "Campaign {$locked->id} is status={$locked->status}; only REVIEW may be rejected."
                );
            }

            $locked->forceFill([
                'status' => 'DRAFT',
                'submitted_at' => null,
                'submitted_by_principal_id' => null,
            ])->save();

            $this->auditLogger->recordCampaignRejected($locked->id, $reason, $actor);

            return $locked;
        });
    }

    /**
     * APPROVED -> PUBLISHED (HD-IMP007-01). BR-1: requires a valid, ACTIVE
     * fund_id — checked HERE, at transition time, under the lock (a Fund
     * archived between DRAFT-save and publish-attempt is caught here, not
     * merely at an earlier save-time check).
     */
    public function publish(Campaign $campaign, Principal $actor): Campaign
    {
        return DB::transaction(function () use ($campaign, $actor) {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'APPROVED') {
                throw new CampaignTransitionConflictException(
                    'invalid_transition',
                    "Campaign {$locked->id} is status={$locked->status}; only APPROVED may be published."
                );
            }

            if ($locked->fund_id === null) {
                throw new CampaignTransitionConflictException(
                    'fund_required',
                    "Campaign {$locked->id} has no Fund assigned and cannot be published."
                );
            }

            $fund = Fund::query()->whereKey($locked->fund_id)->lockForUpdate()->first();

            if ($fund === null || $fund->status !== 'ACTIVE') {
                throw new CampaignTransitionConflictException(
                    'fund_not_active',
                    "Campaign {$locked->id}'s assigned Fund is not ACTIVE and cannot be published against."
                );
            }

            $locked->forceFill([
                'status' => 'PUBLISHED',
                'published_at' => now(),
                'published_by_principal_id' => $actor->id,
            ])->save();

            $this->auditLogger->recordCampaignPublished($locked->id, ['fund_id' => $fund->id], $actor);

            return $locked;
        });
    }

    /**
     * PUBLISHED -> CLOSED. Terminal — no reopen path in v1.
     */
    public function close(Campaign $campaign, Principal $actor): Campaign
    {
        return DB::transaction(function () use ($campaign, $actor) {
            $locked = Campaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'PUBLISHED') {
                throw new CampaignTransitionConflictException(
                    'invalid_transition',
                    "Campaign {$locked->id} is status={$locked->status}; only PUBLISHED may be closed."
                );
            }

            $locked->forceFill([
                'status' => 'CLOSED',
                'closed_at' => now(),
                'closed_by_principal_id' => $actor->id,
            ])->save();

            $this->auditLogger->recordCampaignClosed($locked->id, $actor);

            return $locked;
        });
    }
}
