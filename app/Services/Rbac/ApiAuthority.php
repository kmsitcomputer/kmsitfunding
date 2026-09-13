<?php

namespace App\Services\Rbac;

/**
 * API Authority primitive (see "API Authority"): Effective API Authority =
 * Principal Authority INTERSECT Token Capability — NEVER a union. A token
 * can only narrow what its Principal may do through it, never expand it;
 * conversely the Principal's own authority can never be exceeded by a
 * broader token. IMP-003 defines this primitive only — no token issuance
 * model is implemented here (that belongs to a later API/Integration stage).
 */
final class ApiAuthority
{
    /**
     * @param  string[]  $principalPermissionCodes  every Permission code the Principal
     *                                              effectively holds (via its active Role assignments)
     * @param  string[]  $tokenCapabilityCodes  every capability code the presented token declares
     * @return string[] the intersection — the only codes usable through this token
     */
    public static function effective(array $principalPermissionCodes, array $tokenCapabilityCodes): array
    {
        return array_values(array_intersect($principalPermissionCodes, $tokenCapabilityCodes));
    }
}
