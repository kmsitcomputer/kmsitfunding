<?php

namespace App\Services\Content;

use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use App\Services\Rbac\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * IMP-005 CMS scope resolver (docs/implementation/IMP-005-cms.md section 22
 * "Authorization"): CMS content is organization-owned at ORGANIZATION scope,
 * `scope_id` NULL (single-org platform, `ScopeType::Organization::
 * requiresNullScopeId()` is already true) — no OWN/FUNDRAISER/PARTNER/
 * CAMPAIGN scope exists for CMS resources in this baseline. Mirrors
 * App\Services\Audit\AuditScopeResolver's shape for a scope type with no
 * concrete target row.
 */
final class ContentScopeResolver implements ScopeResolver
{
    public function scopeType(): ScopeType
    {
        return ScopeType::Organization;
    }

    public function resourceMatchesScope(mixed $resource, Principal $principal, ?int $scopeId): bool
    {
        // The evaluator only calls this once the assignment's scope_type
        // already equals ORGANIZATION. There is exactly one organization in
        // this baseline, so no further discrimination is possible or needed.
        return true;
    }

    public function applyToQuery(Builder $query, Principal $principal, ?int $scopeId): Builder
    {
        return $query;
    }

    public function lockAndValidateTarget(int $scopeId): ?object
    {
        // ORGANIZATION has no concrete target row to lock — requiresNullScopeId()
        // is already true, so this is never legitimately reached with a real id.
        return null;
    }
}
