<?php

namespace App\Policies;

use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeAsset;
use App\Policies\Concerns\AuthorizesUsingRbac;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Theme\ThemeScopeResolver;

/**
 * Gates Theme Engine capabilities (docs/implementation/IMP-006-theme-engine.md
 * section 18). ORGANIZATION scope, no ownership rule, no Business Authority —
 * identical shape to ContentPagePolicy. `publish`/`archive` resourceState
 * predicates are the defense-in-depth restatement of section 8's transition
 * table; ThemeActivationService/ThemeService remain the authoritative,
 * transactional guard.
 */
class ThemePolicy
{
    use AuthorizesUsingRbac;

    public function view(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::THEME_VIEW,
        );
    }

    public function viewTheme(Principal $actingPrincipal, Theme $theme): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::THEME_VIEW,
            resource: $theme,
            scopeResolver: new ThemeScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }

    /**
     * CR-001-B (Section 30): "DRAFT Site Design must only be viewable
     * through an authorized Preview action, never a public URL guessable
     * by an anonymous visitor." Same ORGANIZATION-scope shape as every
     * other ThemePolicy method. The controller endpoint that calls this
     * belongs to CR-001-C — this method is the auth gate only.
     */
    public function preview(Principal $actingPrincipal, Theme $theme): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::THEME_PREVIEW,
            resource: $theme,
            scopeResolver: new ThemeScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }

    public function create(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::THEME_CREATE,
        );
    }

    public function update(Principal $actingPrincipal, Theme $theme): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::THEME_UPDATE,
            resource: $theme,
            scopeResolver: new ThemeScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }

    public function publish(Principal $actingPrincipal, Theme $theme): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::THEME_PUBLISH,
            resource: $theme,
            scopeResolver: new ThemeScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (Theme $theme): bool => in_array($theme->status, ['DRAFT', 'INACTIVE', 'ACTIVE'], true),
        );
    }

    public function archive(Principal $actingPrincipal, Theme $theme): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::THEME_ARCHIVE,
            resource: $theme,
            scopeResolver: new ThemeScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (Theme $theme): bool => in_array($theme->status, ['DRAFT', 'INACTIVE'], true),
        );
    }

    /**
     * Section 18: "theme.media.upload ... kept separate from theme.update,
     * mirroring IMP-005 §23's content.media.upload rationale" — the
     * security-sensitive write.
     */
    public function uploadAsset(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::THEME_MEDIA_UPLOAD,
        );
    }

    public function manageAsset(Principal $actingPrincipal, ThemeAsset $asset): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::THEME_UPDATE,
            resource: $asset,
            scopeResolver: new ThemeScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }

    public function archiveAsset(Principal $actingPrincipal, ThemeAsset $asset): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::THEME_ARCHIVE,
            resource: $asset,
            scopeResolver: new ThemeScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }
}
