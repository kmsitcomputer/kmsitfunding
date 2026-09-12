<?php

namespace App\Services\Rbac;

use App\Enums\ScopeType;

/**
 * Whether a HELD scope (from an active Role/Authority assignment) covers a
 * REQUESTED scope (the Target Resource's resolved scope). GLOBAL_PLATFORM is
 * the only scope type the specification defines as broader than another —
 * every other scope type covers only an exact match of its own type and
 * target id (no other containment hierarchy is defined anywhere in
 * DATA-SCOPE-MODEL.md, so none is invented here — unknown containment
 * always denies rather than assuming a hierarchy).
 */
final class ScopeContainment
{
    public static function covers(
        ScopeType $heldType,
        ?int $heldScopeId,
        ScopeType $requestedType,
        ?int $requestedScopeId,
    ): bool {
        if ($heldType === ScopeType::GlobalPlatform) {
            return true;
        }

        if ($heldType !== $requestedType) {
            return false;
        }

        return $heldScopeId === $requestedScopeId;
    }
}
