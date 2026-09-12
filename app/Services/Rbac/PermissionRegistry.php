<?php

namespace App\Services\Rbac;

/**
 * IMP-003 canonical Permission Registry — the CANONICAL SOURCE of Permission
 * codes, versioned in git like any other code. A seeder synchronizes this
 * into the `permissions` table (upsert-by-code, idempotent). Only IMP-003's
 * own `rbac.*` and `identity.security.transition` permissions are defined
 * here — IMP-003 seeds no permission for a domain it does not own. See
 * docs/implementation/IMP-003-rbac-scope-business-authority.md "Permission
 * Model > Permission Registry".
 */
class PermissionRegistry
{
    public const RBAC_ROLE_ASSIGN = 'rbac.role.assign';

    public const RBAC_ROLE_REVOKE = 'rbac.role.revoke';

    public const RBAC_PERMISSION_ASSIGN = 'rbac.permission.assign';

    public const RBAC_PERMISSION_REVOKE = 'rbac.permission.revoke';

    public const RBAC_AUTHORITY_ASSIGN = 'rbac.authority.assign';

    public const RBAC_AUTHORITY_REVOKE = 'rbac.authority.revoke';

    public const IDENTITY_SECURITY_TRANSITION = 'identity.security.transition';

    public const RBAC_PRINCIPAL_DEACTIVATE = 'rbac.principal.deactivate';

    /**
     * @return array<string, array{description: string, module: string}>
     */
    public static function definitions(): array
    {
        return [
            self::RBAC_ROLE_ASSIGN => [
                'description' => 'Assign a canonical Role to another Principal.',
                'module' => 'rbac',
            ],
            self::RBAC_ROLE_REVOKE => [
                'description' => 'Revoke a canonical Role assignment from another Principal.',
                'module' => 'rbac',
            ],
            self::RBAC_PERMISSION_ASSIGN => [
                'description' => 'Grant a Permission to a Role.',
                'module' => 'rbac',
            ],
            self::RBAC_PERMISSION_REVOKE => [
                'description' => 'Revoke a Permission from a Role.',
                'module' => 'rbac',
            ],
            self::RBAC_AUTHORITY_ASSIGN => [
                'description' => 'Grant a Business/Financial Authority Type to another Principal.',
                'module' => 'rbac',
            ],
            self::RBAC_AUTHORITY_REVOKE => [
                'description' => 'Revoke a Business/Financial Authority Assignment from another Principal.',
                'module' => 'rbac',
            ],
            self::IDENTITY_SECURITY_TRANSITION => [
                'description' => 'Transition an identity Lifecycle (ACTIVE<->DISABLED) or Security '.
                    'Restriction (NONE<->SUSPENDED) — IMP-002 defers WHO may do this to IMP-003.',
                'module' => 'identity',
            ],
            self::RBAC_PRINCIPAL_DEACTIVATE => [
                'description' => 'Permanently deactivate a System or Integration Principal catalog '.
                    'entry (and its linked canonical Principal) — a one-way transition.',
                'module' => 'rbac',
            ],
        ];
    }
}
