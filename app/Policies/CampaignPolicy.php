<?php

namespace App\Policies;

use App\Enums\ScopeType;
use App\Models\Campaign\Campaign;
use App\Models\Rbac\Principal;
use App\Policies\Concerns\AuthorizesUsingRbac;
use App\Services\Campaign\CampaignScopeResolver;
use App\Services\Rbac\PermissionRegistry;

/**
 * Gates Campaign capabilities (docs/implementation/
 * IMP-007-campaign-program-fund.md section 14, HD-IMP007-01). ORGANIZATION
 * scope, no ownership rule. `approve()` and `publish()` are DISTINCT
 * methods gated by DISTINCT permissions — CAMPAIGN_APPROVE never implies
 * CAMPAIGN_PUBLISH and vice versa (AC-007-020).
 * `resourceStatePredicate`s are the defense-in-depth restatement of
 * CampaignLifecycleService's own transition guards.
 */
class CampaignPolicy
{
    use AuthorizesUsingRbac;

    public function view(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CAMPAIGN_VIEW,
        );
    }

    public function viewCampaign(Principal $actingPrincipal, Campaign $campaign): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CAMPAIGN_VIEW,
            resource: $campaign,
            scopeResolver: new CampaignScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }

    public function create(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CAMPAIGN_CREATE,
        );
    }

    /**
     * BR-4: DRAFT/REVIEW/APPROVED may be edited; CLOSED rejects all field
     * mutation.
     */
    public function update(Principal $actingPrincipal, Campaign $campaign): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CAMPAIGN_UPDATE,
            resource: $campaign,
            scopeResolver: new CampaignScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (Campaign $campaign): bool => $campaign->status !== 'CLOSED',
        );
    }

    public function submit(Principal $actingPrincipal, Campaign $campaign): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CAMPAIGN_SUBMIT,
            resource: $campaign,
            scopeResolver: new CampaignScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (Campaign $campaign): bool => $campaign->status === 'DRAFT',
        );
    }

    public function approve(Principal $actingPrincipal, Campaign $campaign): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CAMPAIGN_APPROVE,
            resource: $campaign,
            scopeResolver: new CampaignScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (Campaign $campaign): bool => $campaign->status === 'REVIEW',
        );
    }

    public function publish(Principal $actingPrincipal, Campaign $campaign): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CAMPAIGN_PUBLISH,
            resource: $campaign,
            scopeResolver: new CampaignScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (Campaign $campaign): bool => $campaign->status === 'APPROVED',
        );
    }

    public function close(Principal $actingPrincipal, Campaign $campaign): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CAMPAIGN_CLOSE,
            resource: $campaign,
            scopeResolver: new CampaignScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (Campaign $campaign): bool => $campaign->status === 'PUBLISHED',
        );
    }

    public function uploadAsset(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CAMPAIGN_MEDIA_UPLOAD,
        );
    }

    public function archiveAsset(Principal $actingPrincipal, Campaign $campaign): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CAMPAIGN_UPDATE,
            resource: $campaign,
            scopeResolver: new CampaignScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }
}
