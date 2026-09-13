<?php

namespace App\Enums;

/**
 * IMP-004 canonical audit actor attribution kinds. 'human'/'system'/'integration'
 * mirror PrincipalKind (a resolved principals row exists); 'unauthenticated' and
 * 'pre_principal_system' are audit-only values with no PrincipalKind equivalent —
 * they never appear in principals.principal_kind, only on the audit row, and only
 * for registry entries that explicitly declare them (see "Canonical Actor
 * (Pre-Principal Cases)" in docs/implementation/IMP-004-audit-governance-foundation.md).
 */
enum AuditActorKind: string
{
    case Human = 'human';
    case System = 'system';
    case Integration = 'integration';
    case Unauthenticated = 'unauthenticated';
    case PrePrincipalSystem = 'pre_principal_system';

    /**
     * Kinds that require actor_principal_id to reference a real principals row.
     *
     * @return array<int, self>
     */
    public static function principalKinds(): array
    {
        return [self::Human, self::System, self::Integration];
    }

    public function requiresPrincipal(): bool
    {
        return in_array($this, self::principalKinds(), true);
    }
}
