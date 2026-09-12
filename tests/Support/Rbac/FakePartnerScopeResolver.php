<?php

namespace Tests\Support\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use App\Services\Rbac\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * Test-only PARTNER ScopeResolver fixture (see FakePartnerResource) — the
 * real Partner domain resolver belongs to a later, Partner-owning stage.
 */
final class FakePartnerScopeResolver implements ScopeResolver
{
    public function scopeType(): ScopeType
    {
        return ScopeType::Partner;
    }

    public function resourceMatchesScope(mixed $resource, Principal $principal, ?int $scopeId): bool
    {
        return $resource instanceof FakePartnerResource && $resource->partnerId === $scopeId;
    }

    public function applyToQuery(Builder $query, Principal $principal, ?int $scopeId): Builder
    {
        return $query->where('partner_id', $scopeId);
    }
}
