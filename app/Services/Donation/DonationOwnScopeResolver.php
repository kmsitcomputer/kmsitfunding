<?php

namespace App\Services\Donation;

use App\Enums\ScopeType;
use App\Models\Donation\Donation;
use App\Models\Donation\DonationRecurringPlan;
use App\Models\Rbac\Principal;
use App\Services\Rbac\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * IMP-008 — donor OWN-scope resolver (docs/implementation/
 * IMP-008-donation.md "Authorization / RBAC"): own-ness is derived
 * from the resource's donor_principal_id matching the acting
 * Principal — never from a stored scope target row (scope_id is
 * always null for OWN, see ScopeType::requiresNullScopeId()). Covers
 * both Donation and DonationRecurringPlan, whose ownership anchor is
 * the same donor_principal_id column.
 *
 * A NULL donor_principal_id row (guest donation) never matches any
 * Principal — guest rows are unreachable through every OWN path by
 * construction (HD-IMP008-05B), not by an extra conditional.
 */
final class DonationOwnScopeResolver implements ScopeResolver
{
    public function scopeType(): ScopeType
    {
        return ScopeType::Own;
    }

    public function resourceMatchesScope(mixed $resource, Principal $principal, ?int $scopeId): bool
    {
        if (! $resource instanceof Donation && ! $resource instanceof DonationRecurringPlan) {
            return false;
        }

        return $resource->donor_principal_id !== null
            && $resource->donor_principal_id === $principal->id;
    }

    public function applyToQuery(Builder $query, Principal $principal, ?int $scopeId): Builder
    {
        return $query->where('donor_principal_id', $principal->id);
    }

    public function lockAndValidateTarget(int $scopeId): ?object
    {
        return null;
    }
}
