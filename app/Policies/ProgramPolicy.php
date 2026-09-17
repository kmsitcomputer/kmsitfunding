<?php

namespace App\Policies;

use App\Enums\ScopeType;
use App\Models\Campaign\Program;
use App\Models\Rbac\Principal;
use App\Policies\Concerns\AuthorizesUsingRbac;
use App\Services\Campaign\ProgramScopeResolver;
use App\Services\Rbac\PermissionRegistry;

/**
 * Gates Program capabilities (docs/implementation/
 * IMP-007-campaign-program-fund.md section 14). ORGANIZATION scope, no
 * ownership rule — identical shape to ContentPagePolicy/ThemePolicy.
 */
class ProgramPolicy
{
    use AuthorizesUsingRbac;

    public function view(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PROGRAM_VIEW,
        );
    }

    public function viewProgram(Principal $actingPrincipal, Program $program): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PROGRAM_VIEW,
            resource: $program,
            scopeResolver: new ProgramScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }

    public function create(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PROGRAM_CREATE,
        );
    }

    public function update(Principal $actingPrincipal, Program $program): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PROGRAM_UPDATE,
            resource: $program,
            scopeResolver: new ProgramScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }

    public function publish(Principal $actingPrincipal, Program $program): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PROGRAM_PUBLISH,
            resource: $program,
            scopeResolver: new ProgramScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (Program $program): bool => in_array($program->status, ['DRAFT', 'PUBLISHED'], true),
        );
    }

    public function archive(Principal $actingPrincipal, Program $program): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PROGRAM_ARCHIVE,
            resource: $program,
            scopeResolver: new ProgramScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (Program $program): bool => in_array($program->status, ['DRAFT', 'PUBLISHED'], true),
        );
    }

    public function uploadAsset(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PROGRAM_MEDIA_UPLOAD,
        );
    }

    public function archiveAsset(Principal $actingPrincipal, Program $program): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PROGRAM_ARCHIVE,
            resource: $program,
            scopeResolver: new ProgramScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }
}
