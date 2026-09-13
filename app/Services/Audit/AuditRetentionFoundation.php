<?php

namespace App\Services\Audit;

use App\Models\Audit\AuditRecord;
use Illuminate\Database\Eloquent\Builder;

/**
 * IMP-004 retention foundation (Q28/Q17 mechanism only — structural hooks,
 * NOT a retention engine). No retention durations, eligibility rules, hold
 * mechanism, or purge schedule are invented here; those remain configurable
 * through a future authorized, versioned policy.
 *
 * The one rule this stage DOES implement is the Terminal Purge-Evidence Rule
 * (IMP004-SPEC-m01): a governance.audit.purged record is excluded, permanently
 * and unconditionally, from the eligibility set of the SAME purge batch that
 * produced it — preventing unbounded recursive purge-evidence-of-purge-
 * evidence generation.
 */
final class AuditRetentionFoundation
{
    public const PURGE_EVIDENCE_EVENT_TYPE = 'governance.audit.purged';

    /**
     * Candidate eligibility set for a (future) purge batch: every audit
     * record EXCEPT the purge-evidence records produced by this same batch.
     * A future governed retention flow composes its own authorized policy
     * constraints on top of this terminal exclusion.
     */
    public function eligibleForPurgeBatch(string $purgeBatchId): Builder
    {
        return AuditRecord::query()->whereNot(function (Builder $query) use ($purgeBatchId) {
            $query->where('event_type', self::PURGE_EVIDENCE_EVENT_TYPE)
                ->where('metadata->purge_batch_id', $purgeBatchId);
        });
    }
}
