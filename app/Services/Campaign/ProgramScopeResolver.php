<?php

namespace App\Services\Campaign;

use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use App\Services\Rbac\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * IMP-007 (docs/implementation/IMP-007-campaign-program-fund.md section 6) —
 * v1 uses ScopeType::Organization, mirroring ContentScopeResolver/
 * ThemeScopeResolver exactly, since no scoped staff-assignment role exists
 * yet to populate a real scope_id for the locked ScopeType::Program value.
 */
final class ProgramScopeResolver implements ScopeResolver
{
    public function scopeType(): ScopeType
    {
        return ScopeType::Organization;
    }

    public function resourceMatchesScope(mixed $resource, Principal $principal, ?int $scopeId): bool
    {
        return true;
    }

    public function applyToQuery(Builder $query, Principal $principal, ?int $scopeId): Builder
    {
        return $query;
    }

    public function lockAndValidateTarget(int $scopeId): ?object
    {
        return null;
    }
}
