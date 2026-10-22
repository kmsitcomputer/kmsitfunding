<?php

namespace App\Services\Payment;

use App\Enums\ScopeType;
use App\Models\Payment\Payment;
use App\Models\Rbac\Principal;
use App\Services\Rbac\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * IMP-009 — donor OWN-scope resolver (docs/implementation/
 * IMP-009-payment-hub.md "Authorization"): own-ness is derived from the
 * owning Donation's donor_principal_id matching the acting Principal —
 * never from a stored scope target row (scope_id is always null for
 * OWN, see ScopeType::requiresNullScopeId()).
 *
 * A NULL donor_principal_id row (guest donation) never matches any
 * Principal — guest rows are unreachable through every OWN path by
 * construction (HD-IMP009-04), not by an extra conditional.
 */
final class PaymentOwnScopeResolver implements ScopeResolver
{
    public function scopeType(): ScopeType
    {
        return ScopeType::Own;
    }

    public function resourceMatchesScope(mixed $resource, Principal $principal, ?int $scopeId): bool
    {
        if (! $resource instanceof Payment) {
            return false;
        }

        return $resource->relationLoaded('donation')
            ? $this->matches($resource->donation?->donor_principal_id, $principal)
            : $this->matches($resource->donation()->first()?->donor_principal_id, $principal);
    }

    public function applyToQuery(Builder $query, Principal $principal, ?int $scopeId): Builder
    {
        return $query->whereHas('donation', fn (Builder $donations) => $donations->where('donor_principal_id', $principal->id));
    }

    public function lockAndValidateTarget(int $scopeId): ?object
    {
        return null;
    }

    private function matches(?int $donorPrincipalId, Principal $principal): bool
    {
        return $donorPrincipalId !== null && $donorPrincipalId === $principal->id;
    }
}
