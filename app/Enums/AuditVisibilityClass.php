<?php

namespace App\Enums;

use App\Services\Rbac\PermissionRegistry;

/**
 * IMP-004 registry-derived visibility class (IMP004-SPEC-M05) — a closed list,
 * fixed per (event_type, event_version) registry entry, never caller-selectable.
 * Each class maps to exactly one audit.read.* base permission; Super Admin is
 * not granted any of them by default.
 */
enum AuditVisibilityClass: string
{
    case General = 'general';
    case Security = 'security';
    case FinancialReference = 'financial_reference';

    public function requiredPermission(): string
    {
        return match ($this) {
            self::General => PermissionRegistry::AUDIT_READ,
            self::Security => PermissionRegistry::AUDIT_READ_SECURITY,
            self::FinancialReference => PermissionRegistry::AUDIT_READ_FINANCIAL_REFERENCE,
        };
    }
}
