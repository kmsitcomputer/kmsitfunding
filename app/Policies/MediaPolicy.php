<?php

namespace App\Policies;

use App\Enums\ScopeType;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Rbac\Principal;
use App\Policies\Concerns\AuthorizesUsingRbac;
use App\Services\Content\ContentScopeResolver;
use App\Services\Rbac\PermissionRegistry;

/**
 * Gates CMS media capabilities (docs/implementation/IMP-005-cms.md section
 * 23: content.media.upload is kept separate from content.update as "the
 * security-sensitive write"). Archive/purge are content.archive-gated
 * MediaService operations (section 19) added in the services slice, once
 * there is a transaction for a policy method to gate.
 */
class MediaPolicy
{
    use AuthorizesUsingRbac;

    public function upload(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_MEDIA_UPLOAD,
        );
    }

    public function manage(Principal $actingPrincipal, CmsMediaAsset $asset): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::CONTENT_VIEW,
            resource: $asset,
            scopeResolver: new ContentScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }
}
