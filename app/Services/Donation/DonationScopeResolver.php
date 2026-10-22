<?php

namespace App\Services\Donation;

use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use App\Services\Rbac\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * IMP-008 (docs/implementation/IMP-008-donation.md "Authorization / RBAC",
 * section 6) — v1 uses ScopeType::Organization for the admin/staff path,
 * mirroring CampaignScopeResolver/FundScopeResolver exactly, since no
 * scoped staff-assignment role exists yet. The donor OWN path is enforced
 * by DonationPolicy ownershipCheck closures (donor_principal_id ==
 * acting Principal), not by this resolver.
 */
final class DonationScopeResolver implements ScopeResolver
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
