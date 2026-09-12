<?php

namespace App\Enums;

/**
 * IMP-003 canonical Data Scope taxonomy — reused verbatim from
 * docs/05-rbac/DATA-SCOPE-MODEL.md, no new value invented. See
 * docs/implementation/IMP-003-rbac-scope-business-authority.md "Scope Type / Scope Target
 * Matrix" for which of these require a non-null scope_id.
 */
enum ScopeType: string
{
    case Own = 'OWN';
    case Fundraiser = 'FUNDRAISER';
    case Partner = 'PARTNER';
    case Campaign = 'CAMPAIGN';
    case Program = 'PROGRAM';
    case Fund = 'FUND';
    case BeneficiaryCase = 'BENEFICIARY_CASE';
    case AssignedWork = 'ASSIGNED_WORK';
    case Organization = 'ORGANIZATION';
    case GlobalPlatform = 'GLOBAL_PLATFORM';

    /**
     * Scope types with no concrete target row — scope_id MUST be null for these.
     */
    public function requiresNullScopeId(): bool
    {
        return match ($this) {
            self::GlobalPlatform, self::Organization, self::Own => true,
            default => false,
        };
    }
}
