<?php

namespace App\Policies;

use App\Enums\ScopeType;
use App\Models\Campaign\Fund;
use App\Models\Rbac\Principal;
use App\Policies\Concerns\AuthorizesUsingRbac;
use App\Services\Campaign\FundScopeResolver;
use App\Services\Rbac\PermissionRegistry;

/**
 * Gates Fund capabilities (docs/implementation/
 * IMP-007-campaign-program-fund.md section 14). ORGANIZATION scope, no
 * ownership rule.
 */
class FundPolicy
{
    use AuthorizesUsingRbac;

    public function view(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::FUND_VIEW,
        );
    }

    public function create(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::FUND_CREATE,
        );
    }

    public function update(Principal $actingPrincipal, Fund $fund): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::FUND_UPDATE,
            resource: $fund,
            scopeResolver: new FundScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }

    public function archive(Principal $actingPrincipal, Fund $fund): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::FUND_ARCHIVE,
            resource: $fund,
            scopeResolver: new FundScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }
}
