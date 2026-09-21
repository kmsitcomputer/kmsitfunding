<?php

namespace App\Services\Zakat;

use App\Models\Zakat\ZakatPolicy;
use App\Models\Zakat\ZakatType;
use App\ValueObjects\Zakat\CalculationInput;
use App\ValueObjects\Zakat\CalculationResult;

/**
 * CR-001-B — Zakat calculator application-service contract
 * (docs/change-requests/CR-001-public-experience-cms-ziswaf-admin-v2.md
 * Section 16/20/55). `resolveApplicablePolicy()` is real, testable
 * date-range/status lookup logic — no formula involved. `calculate()`
 * establishes the accepted signature but deliberately does NOT compute a
 * due amount: the actual Zakat formula, nisab comparison, and rate
 * application belong to IMP-019 and are never invented here (Section 55,
 * "CALCULATOR BUSINESS RULES / RATES / FORMULAS" is out of CR-001-B scope).
 * This service never creates a Payment Attempt or Donation directly
 * (Section 22 boundary).
 *
 * CODEX-CR001B-02 remediation (Round 2): `resolveApplicablePolicy()`
 * previously used `orderByDesc(...)->first()`, which would silently pick
 * an arbitrary "winner" if governance had somehow been bypassed and more
 * than one ACTIVE policy applied to the same date — hiding exactly the
 * kind of corrupted/ambiguous history this finding is about. It now
 * FAILS CLOSED instead: zero matches returns null (existing contract,
 * unchanged), exactly one match returns it, and more than one throws
 * rather than guessing.
 */
class ZakatCalculatorService
{
    public function resolveApplicablePolicy(ZakatType $zakatType, \DateTimeImmutable $asOfDate): ?ZakatPolicy
    {
        $asOf = $asOfDate->format('Y-m-d');

        $applicable = ZakatPolicy::query()
            ->where('zakat_type_id', $zakatType->id)
            ->where('status', 'ACTIVE')
            ->where('effective_from', '<=', $asOf)
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('effective_until')->orWhere('effective_until', '>=', $asOf);
            })
            ->get();

        if ($applicable->count() > 1) {
            throw new \RuntimeException(
                'Ambiguous ACTIVE ZakatPolicy state: more than one policy applies to this ZakatType and date. '.
                'Refusing to select an arbitrary winner — this indicates a data integrity issue requiring review.'
            );
        }

        return $applicable->first();
    }

    public function calculate(ZakatPolicy $policy, CalculationInput $input): CalculationResult
    {
        throw new \LogicException(
            'ZakatCalculatorService::calculate() formula is not yet implemented. '.
            'CR-001-B establishes the contract only; the Zakat rate/nisab/formula belongs to IMP-019.'
        );
    }
}
