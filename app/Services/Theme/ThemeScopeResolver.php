<?php

namespace App\Services\Theme;

use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use App\Services\Rbac\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * IMP-006 Theme Engine scope resolver (docs/implementation/
 * IMP-006-theme-engine.md section 18): theme configuration is
 * organization-owned at ORGANIZATION scope, `scope_id` NULL — identical
 * shape to App\Services\Content\ContentScopeResolver (single-org platform,
 * no new scope dimension invented).
 */
final class ThemeScopeResolver implements ScopeResolver
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
