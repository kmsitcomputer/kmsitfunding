<?php

namespace App\Services\Donation;

use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use App\Services\Rbac\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * IMP-008 — Recurring Plan ORGANIZATION-scope resolver (admin override
 * path, HD-IMP008-03), identical shape to DonationScopeResolver. The donor
 * OWN path is enforced by RecurringPlanPolicy ownershipCheck closures.
 */
final class RecurringPlanScopeResolver implements ScopeResolver
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
