<?php

namespace App\Services\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The only ScopeResolver IMP-003 itself implements: OWN scope against the
 * Principal's own User identity. scope_id is always null for OWN (see
 * ScopeType::requiresNullScopeId()) — "own-ness" is derived from the
 * Principal's human_user_id, not from a stored scope target row.
 */
class OwnUserScopeResolver implements ScopeResolver
{
    public function scopeType(): ScopeType
    {
        return ScopeType::Own;
    }

    public function resourceMatchesScope(mixed $resource, Principal $principal, ?int $scopeId): bool
    {
        if (! $resource instanceof User || $principal->human_user_id === null) {
            return false;
        }

        return $resource->id === $principal->human_user_id;
    }

    public function applyToQuery(Builder $query, Principal $principal, ?int $scopeId): Builder
    {
        if ($principal->human_user_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($query->getModel()->getQualifiedKeyName(), $principal->human_user_id);
    }
}
