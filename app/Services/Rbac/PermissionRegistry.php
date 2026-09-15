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

    // IMP-004 audit-read permission family (Q27 / IMP004-SPEC-M05). Super
    // Admin is NOT granted any of these by default — each requires its own
    // explicit Role/Permission grant through RolePermissionService. None of
    // them grants any financial-domain business authority.
    public const AUDIT_READ = 'audit.read';

    public const AUDIT_READ_SECURITY = 'audit.read.security';

    public const AUDIT_READ_FINANCIAL_REFERENCE = 'audit.read.financial_reference';

    // IMP-005 CMS content permission family (docs/implementation/IMP-005-cms.md
    // section 23 "Permissions (smallest coherent set)"). Included in the
    // super_admin bulk grant below like every other non-audit permission —
    // no bypass, no role-name check (section 22).
    public const CONTENT_VIEW = 'content.view';

    public const CONTENT_CREATE = 'content.create';

    public const CONTENT_UPDATE = 'content.update';

    public const CONTENT_PUBLISH = 'content.publish';

    public const CONTENT_ARCHIVE = 'content.archive';

    public const CONTENT_MEDIA_UPLOAD = 'content.media.upload';

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
            self::AUDIT_READ => [
                'description' => 'Read canonical audit evidence whose registry visibility class is GENERAL '.
                    '(with no populated financial-reference fields).',
                'module' => 'audit',
            ],
            self::AUDIT_READ_SECURITY => [
                'description' => 'Read canonical audit evidence whose registry visibility class is SECURITY.',
                'module' => 'audit',
            ],
            self::AUDIT_READ_FINANCIAL_REFERENCE => [
                'description' => 'Read otherwise-authorized canonical audit evidence containing protected '.
                    'financial references — grants NO financial-domain business authority, Payment/Ledger/'.
                    'Withdrawal/Refund/Reconciliation access, or scope bypass.',
                'module' => 'audit',
            ],
            self::CONTENT_VIEW => [
                'description' => 'Read/manage-list pages, articles, revisions, history, media (read/edit browse plane).',
                'module' => 'content',
            ],
            self::CONTENT_CREATE => [
                'description' => 'Create pages/articles (and their initial drafts).',
                'module' => 'content',
            ],
            self::CONTENT_UPDATE => [
                'description' => 'Edit drafts, replace a draft revision, slug/title changes on non-published content, media metadata.',
                'module' => 'content',
            ],
            self::CONTENT_PUBLISH => [
                'description' => 'Publish/unpublish/re-publish; schedule configuration/change/cancellation; homepage assignment.',
                'module' => 'content',
            ],
            self::CONTENT_ARCHIVE => [
                'description' => 'Archive a page/article (terminal lifecycle) and media logical archive; governed path/redirect release.',
                'module' => 'content',
            ],
            self::CONTENT_MEDIA_UPLOAD => [
                'description' => 'Upload intake of content media — the security-sensitive write, kept separate from content.update.',
                'module' => 'content',
            ],
        ];
    }
}
