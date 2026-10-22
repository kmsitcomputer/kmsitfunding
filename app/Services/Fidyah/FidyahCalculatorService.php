<?php

namespace App\Services\Fidyah;

use App\Models\Fidyah\FidyahPolicy;
use App\ValueObjects\Fidyah\CalculationInput;
use App\ValueObjects\Fidyah\CalculationResult;

/**
 * CR-001-B — Fidyah calculator application-service contract, mirroring
 * App\Services\Zakat\ZakatCalculatorService exactly (docs/change-requests/
 * CR-001-public-experience-cms-ziswaf-admin-v2.md Section 16/20/55).
 * `calculate()` deliberately does NOT compute a due amount — the actual
 * rate-per-day formula belongs to IMP-019.
 *
 * CODEX-CR001B-02 remediation (Round 2): `resolveApplicablePolicy()` now
 * fails closed on ambiguous (>1) ACTIVE matches instead of silently
 * picking the latest by `effective_from` — mirrors
 * App\Services\Zakat\ZakatCalculatorService exactly.
 */
class FidyahCalculatorService
{
    public function resolveApplicablePolicy(\DateTimeImmutable $asOfDate): ?FidyahPolicy
    {
        $asOf = $asOfDate->format('Y-m-d');

        $applicable = FidyahPolicy::query()
            ->where('status', 'ACTIVE')
            ->where('effective_from', '<=', $asOf)
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('effective_until')->orWhere('effective_until', '>=', $asOf);
            })
            ->get();

        if ($applicable->count() > 1) {
            throw new \RuntimeException(
                'Ambiguous ACTIVE FidyahPolicy state: more than one policy applies to this date. Refusing to '.
                'select an arbitrary winner — this indicates a data integrity issue requiring review.'
            );
        }

        return $applicable->first();
    }

    public function calculate(FidyahPolicy $policy, CalculationInput $input): CalculationResult
    {
        throw new \LogicException(
            'FidyahCalculatorService::calculate() formula is not yet implemented. '.
            'CR-001-B establishes the contract only; the Fidyah rate/formula belongs to IMP-019.'
        );
    }
}
