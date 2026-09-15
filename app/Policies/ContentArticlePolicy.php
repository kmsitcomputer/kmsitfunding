<?php

namespace App\Policies;

use App\Enums\ScopeType;
use App\Models\Cms\CmsArticle;
use App\Models\Rbac\Principal;
use App\Policies\Concerns\AuthorizesUsingRbac;
use App\Services\Content\ContentScopeResolver;
use App\Services\Rbac\PermissionRegistry;

/**
 * Gates the CMS Article capabilities. Identical shape to ContentPagePolicy
 * — docs/implementation/IMP-005-cms.md section 23 applies the same five
 * content.* permission codes to Pages and Articles (no per-entity
 * permission explosion, and News is Article classification, not a
 * separate entity — Q33).
 */
class ContentArticlePolicy
{
    use AuthorizesUsingRbac;

    public function view(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_VIEW,
        );
    }

    public function viewArticle(Principal $actingPrincipal, CmsArticle $article): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_VIEW,
            resource: $article,
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

    public function update(Principal $actingPrincipal, CmsArticle $article): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_UPDATE,
            resource: $article,
            scopeResolver: new ContentScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }

    public function publish(Principal $actingPrincipal, CmsArticle $article): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_PUBLISH,
            resource: $article,
            scopeResolver: new ContentScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (CmsArticle $article): bool => in_array($article->status, ['DRAFT', 'RETIRED', 'PUBLISHED'], true),
        );
    }

    public function archive(Principal $actingPrincipal, CmsArticle $article): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_ARCHIVE,
            resource: $article,
            scopeResolver: new ContentScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (CmsArticle $article): bool => in_array($article->status, ['DRAFT', 'RETIRED'], true),
        );
    }
}
