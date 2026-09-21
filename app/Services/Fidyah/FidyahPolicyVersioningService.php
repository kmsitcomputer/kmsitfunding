<?php

namespace App\Services\Fidyah;

use App\Models\Fidyah\FidyahPolicy;
use App\Models\PolicyLeadTimeConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CODEX-CR001B-02 remediation (Round 1 + Round 2) — the sole authoritative
 * write boundary for FidyahPolicy versions, mirroring
 * App\Services\Zakat\ZakatPolicyVersioningService exactly (see that
 * class's docblock for the full Round 2 rationale), minus the ZakatType
 * dimension (Fidyah policy is org-wide, not per-type).
 */
class FidyahPolicyVersioningService
{
    private const LOCK_KEY = 'fidyah_policy';

    /**
     * @param  array<string, mixed>  $attributes  rate_amount_minor, currency, source_ref,
     *                                            effective_from, effective_until, status,
     *                                            created_by_principal_id, updated_by_principal_id
     */
    public function publishNewVersion(array $attributes): FidyahPolicy
    {
        $status = $attributes['status'] ?? 'DRAFT';
        $effectiveFrom = Carbon::parse($attributes['effective_from'])->startOfDay();
        $effectiveUntil = isset($attributes['effective_until']) && $attributes['effective_until'] !== null
            ? Carbon::parse($attributes['effective_until'])->startOfDay()
            : null;

        if ($effectiveUntil !== null && $effectiveUntil->lt($effectiveFrom)) {
            throw new \InvalidArgumentException('effective_until must not be before effective_from.');
        }

        return DB::transaction(function () use ($attributes, $status, $effectiveFrom, $effectiveUntil) {
            $this->acquireLock();

            try {
                if ($status === 'ACTIVE') {
                    $leadDays = PolicyLeadTimeConfig::current()->requiredLeadTimeDays();
                    $earliestAllowed = Carbon::today()->addDays($leadDays);

                    if ($effectiveFrom->lt($earliestAllowed)) {
                        throw new \LogicException(
                            "effective_from ({$effectiveFrom->toDateString()}) violates the configured minimum ".
                            "lead time of {$leadDays} day(s); the earliest permitted date is {$earliestAllowed->toDateString()}."
                        );
                    }

                    $existingActive = FidyahPolicy::query()
                        ->where('status', 'ACTIVE')
                        ->lockForUpdate()
                        ->get();

                    foreach ($existingActive as $existing) {
                        if ($this->rangesOverlap($existing->effective_from, $existing->effective_until, $effectiveFrom, $effectiveUntil)) {
                            throw new \LogicException(
                                'An overlapping ACTIVE FidyahPolicy already exists; overlapping effective '.
                                'periods are not permitted.'
                            );
                        }
                    }
                }

                $nextVersion = (int) FidyahPolicy::query()->max('version') + 1;

                $policy = FidyahPolicy::create(array_merge($attributes, [
                    'ulid' => (string) Str::ulid(),
                    'version' => $nextVersion,
                    'status' => 'DRAFT',
                    'effective_from' => $effectiveFrom->toDateString(),
                    'effective_until' => $effectiveUntil?->toDateString(),
                ]));

                if ($status !== 'DRAFT') {
                    DB::table('fidyah_policies')
                        ->where('id', $policy->getKey())
                        ->where('status', 'DRAFT')
                        ->update(['status' => $status, 'updated_at' => now()]);

                    $policy->refresh();
                }

                return $policy;
            } finally {
                $this->releaseLock();
            }
        });
    }

    private function acquireLock(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $acquired = DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [self::LOCK_KEY])->acquired ?? null;

        if ((int) $acquired !== 1) {
            throw new \RuntimeException(
                'Could not acquire the FidyahPolicy write lock within the timeout; another write is in '.
                'progress (fail-closed).'
            );
        }
    }

    private function releaseLock(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SELECT RELEASE_LOCK(?)', [self::LOCK_KEY]);
        }
    }

    private function rangesOverlap(mixed $aFrom, mixed $aUntil, Carbon $bFrom, ?Carbon $bUntil): bool
    {
        $aFrom = Carbon::parse($aFrom);
        $aUntil = $aUntil !== null ? Carbon::parse($aUntil) : null;

        $aEndsOnOrAfterBStarts = $aUntil === null || $aUntil->gte($bFrom);
        $bEndsOnOrAfterAStarts = $bUntil === null || $bUntil->gte($aFrom);

        return $aEndsOnOrAfterBStarts && $bEndsOnOrAfterAStarts;
    }
}
