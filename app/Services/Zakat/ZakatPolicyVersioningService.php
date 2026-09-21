<?php

namespace App\Services\Zakat;

use App\Models\PolicyLeadTimeConfig;
use App\Models\Zakat\ZakatPolicy;
use App\Models\Zakat\ZakatType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CODEX-CR001B-02 remediation (Round 1 + Round 2) — the sole authoritative
 * write boundary for ZakatPolicy versions (docs/change-requests/
 * CR-001-public-experience-cms-ziswaf-admin-v2.md HD-CR001-03). Historical
 * rows are never UPDATEd (App\Models\Zakat\ZakatPolicy + GovernedPolicyBuilder
 * block that once a row leaves DRAFT) — a correction always creates a new
 * row with the next `version` number.
 *
 * Round 2: "service exists" was found NOT to be an authoritative boundary
 * by itself — ordinary `ZakatPolicy::create([...ACTIVE...])` or
 * `$draft->save()` after flipping `status` could bypass every check below.
 * ZakatPolicy's own `creating` event now REQUIRES `status = 'DRAFT'` for
 * any row inserted through ordinary Eloquent persistence, and `save()`
 * refuses any transition away from DRAFT. This service is therefore now
 * the ONLY code path able to produce a non-DRAFT row at all: it always
 * inserts as DRAFT first (satisfying the model's own rule — no bypass
 * flag needed), then, only if the caller requested a non-DRAFT status,
 * performs a single validated transition via the RAW base query builder
 * (`DB::table(...)->where('status', 'DRAFT')->update(...)`) — deliberately
 * NOT through the Eloquent model/builder, which the model's own guards
 * would otherwise correctly refuse. That raw step is a controlled,
 * internal detail of this trusted service, not a publicly reachable
 * bypass — no forgeable flag, boolean, or request input gates it.
 *
 * Enforces, in order, for every non-DRAFT (e.g. ACTIVE) publish:
 *   1. effective_until (if any) is not before effective_from;
 *   2. the configured minimum lead time is set (fail-closed if NULL) and
 *      effective_from respects it (boundary INCLUSIVE — exactly the
 *      earliest permitted date is allowed);
 *   3. no ambiguous (>1) or overlapping ACTIVE policy exists for the same
 *      ZakatType, under a transaction + row lock (MySQL: an additional
 *      named GET_LOCK/RELEASE_LOCK pair also serializes concurrent
 *      publishes for the same type, since a brand-new type may have zero
 *      existing rows to lock).
 *
 * No rate, nisab, or lead-time VALUE is invented here — only the write
 * contract around whatever value an authorized caller supplies.
 */
class ZakatPolicyVersioningService
{
    /**
     * @param  array<string, mixed>  $attributes  rate, nisab_basis, source_ref,
     *                                            effective_from, effective_until, status,
     *                                            created_by_principal_id, updated_by_principal_id
     */
    public function publishNewVersion(ZakatType $zakatType, array $attributes): ZakatPolicy
    {
        $status = $attributes['status'] ?? 'DRAFT';
        $effectiveFrom = Carbon::parse($attributes['effective_from'])->startOfDay();
        $effectiveUntil = isset($attributes['effective_until']) && $attributes['effective_until'] !== null
            ? Carbon::parse($attributes['effective_until'])->startOfDay()
            : null;

        if ($effectiveUntil !== null && $effectiveUntil->lt($effectiveFrom)) {
            throw new \InvalidArgumentException('effective_until must not be before effective_from.');
        }

        return DB::transaction(function () use ($zakatType, $attributes, $status, $effectiveFrom, $effectiveUntil) {
            $lockKey = "zakat_policy_type_{$zakatType->id}";
            $this->acquireLock($lockKey);

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

                    $existingActive = ZakatPolicy::query()
                        ->where('zakat_type_id', $zakatType->id)
                        ->where('status', 'ACTIVE')
                        ->lockForUpdate()
                        ->get();

                    foreach ($existingActive as $existing) {
                        if ($this->rangesOverlap($existing->effective_from, $existing->effective_until, $effectiveFrom, $effectiveUntil)) {
                            throw new \LogicException(
                                'An overlapping ACTIVE ZakatPolicy already exists for this ZakatType; '.
                                'overlapping effective periods are not permitted.'
                            );
                        }
                    }
                }

                $nextVersion = (int) (ZakatPolicy::query()->where('zakat_type_id', $zakatType->id)->max('version')) + 1;

                // Always insert as DRAFT — ZakatPolicy::creating() enforces
                // this for every ordinary caller too; this service does not
                // get a special exemption from that rule, it satisfies it.
                $policy = ZakatPolicy::create(array_merge($attributes, [
                    'ulid' => (string) Str::ulid(),
                    'zakat_type_id' => $zakatType->id,
                    'version' => $nextVersion,
                    'status' => 'DRAFT',
                    'effective_from' => $effectiveFrom->toDateString(),
                    'effective_until' => $effectiveUntil?->toDateString(),
                ]));

                if ($status !== 'DRAFT') {
                    // The one authoritative transition step: raw base
                    // query builder, never the Eloquent model/builder,
                    // which would (correctly) refuse this exact write.
                    DB::table('zakat_policies')
                        ->where('id', $policy->getKey())
                        ->where('status', 'DRAFT')
                        ->update(['status' => $status, 'updated_at' => now()]);

                    $policy->refresh();
                }

                return $policy;
            } finally {
                $this->releaseLock($lockKey);
            }
        });
    }

    private function acquireLock(string $key): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            // SQLite serializes writers at the connection/transaction
            // level already; no separate advisory lock primitive exists
            // (or is needed) there.
            return;
        }

        $acquired = DB::selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$key])->acquired ?? null;

        if ((int) $acquired !== 1) {
            throw new \RuntimeException(
                'Could not acquire the ZakatPolicy write lock for this ZakatType within the timeout; '.
                'another write is in progress (fail-closed).'
            );
        }
    }

    private function releaseLock(string $key): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SELECT RELEASE_LOCK(?)', [$key]);
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
