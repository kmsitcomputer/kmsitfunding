<?php

namespace App\Policies;

use App\Enums\ScopeType;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use App\Policies\Concerns\AuthorizesUsingRbac;
use App\Services\Content\ContentScopeResolver;
use App\Services\Rbac\PermissionRegistry;

/**
 * Gates the CMS Page capabilities (docs/implementation/IMP-005-cms.md
 * section 22 "Authorization" + section 23 "Permissions"). ORGANIZATION
 * scope, STANDARD assurance (CMS publishing is not a listed high-risk
 * category — never requiresElevatedAssurance), no ownership rule, no
 * Business Authority (inventing a content_authority type is FORBIDDEN).
 *
 * `publish`/`archive` resourceStatePredicate is a DEFENSE-IN-DEPTH
 * authorization-layer restatement of section 10's transition table — the
 * service layer (not built in this slice) remains the authoritative,
 * transactional guard per section 10 "Transition discipline".
 */
class ContentPagePolicy
{
    use AuthorizesUsingRbac;

    public function view(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_VIEW,
        );
    }

    public function viewPage(Principal $actingPrincipal, CmsPage $page): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_VIEW,
            resource: $page,
            scopeResolver: new ContentScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }

    public function create(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_CREATE,
        );
    }

    public function update(Principal $actingPrincipal, CmsPage $page): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_UPDATE,
            resource: $page,
            scopeResolver: new ContentScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }

    public function publish(Principal $actingPrincipal, CmsPage $page): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_PUBLISH,
            resource: $page,
            scopeResolver: new ContentScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (CmsPage $page): bool => in_array($page->status, ['DRAFT', 'RETIRED', 'PUBLISHED'], true),
        );
    }

    /**
     * Section 23: "content.publish ... homepage assignment" — the same
     * PUBLICATION-plane permission, GLOBAL (no single page resource to
     * scope against; PublicationService::setHomepage() itself rejects an
     * ARCHIVED target).
     */
    public function assignHomepage(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_PUBLISH,
        );
    }

    public function archive(Principal $actingPrincipal, CmsPage $page): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_ARCHIVE,
            resource: $page,
            scopeResolver: new ContentScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (CmsPage $page): bool => in_array($page->status, ['DRAFT', 'RETIRED'], true),
        );
    }
}
