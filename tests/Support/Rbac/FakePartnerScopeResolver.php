<?php

namespace Tests\Support\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use App\Services\Rbac\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * Test-only PARTNER ScopeResolver fixture (see FakePartnerResource) — the
 * real Partner domain resolver belongs to a later, Partner-owning stage.
 * Backed by an explicit in-memory set of "active" target ids (rather than a
 * real table, since no Partner domain table exists) so tests can exercise
 * `IMP003-IMPL-M02`'s "unknown/inactive target -> REJECT" contract without
 * inventing a premature domain schema.
 */
final class FakePartnerScopeResolver implements ScopeResolver
{
    /**
     * @param  int[]  $activePartnerIds
     */
    public function __construct(private readonly array $activePartnerIds = []) {}

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

    public function lockAndValidateTarget(int $scopeId): ?object
    {
        return in_array($scopeId, $this->activePartnerIds, true) ? new FakePartnerResource($scopeId) : null;
    }
}
