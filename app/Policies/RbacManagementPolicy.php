<?php

namespace App\Policies;

use App\Models\Rbac\Principal;
use App\Policies\Concerns\AuthorizesUsingRbac;
use App\Services\Rbac\PermissionRegistry;

/**
 * Gates the rbac.* management capabilities and identity.security.transition
 * (see "Permission Model" and "Security Restriction"). No scope resolver is
 * supplied here: IMP-003 does not define any scope narrower than
 * GLOBAL_PLATFORM for RBAC management itself (the only Role IMP-003 seeds,
 * `super_admin`, is GLOBAL_PLATFORM-scoped) — a narrower delegated RBAC
 * management scope is not specified anywhere and is therefore not invented.
 *
 * The self-target denial below is DEFENSE IN DEPTH, not the authoritative
 * enforcement point — RoleAssignmentService/AuthorityAssignmentService
 * reject self-escalation unconditionally regardless of what this Policy
 * allows (see "Self-Escalation Protection").
 */
class RbacManagementPolicy
{
    use AuthorizesUsingRbac;

    public function assignRole(Principal $actingPrincipal, Principal $target): bool
    {
        return $actingPrincipal->id !== $target->id
            && $this->authorizeRbac(
                principal: $actingPrincipal,
                permissionCode: PermissionRegistry::RBAC_ROLE_ASSIGN,
                requiresElevatedAssurance: true,
            );
    }

    public function revokeRole(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::RBAC_ROLE_REVOKE,
            requiresElevatedAssurance: true,
        );
    }

    public function assignPermission(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::RBAC_PERMISSION_ASSIGN,
            requiresElevatedAssurance: true,
        );
    }

    public function revokePermission(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::RBAC_PERMISSION_REVOKE,
            requiresElevatedAssurance: true,
        );
    }

    public function assignAuthority(Principal $actingPrincipal, Principal $target): bool
    {
        return $actingPrincipal->id !== $target->id
            && $this->authorizeRbac(
                principal: $actingPrincipal,
                permissionCode: PermissionRegistry::RBAC_AUTHORITY_ASSIGN,
                requiresElevatedAssurance: true,
            );
    }

    public function revokeAuthority(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::RBAC_AUTHORITY_REVOKE,
            requiresElevatedAssurance: true,
        );
    }

    public function transitionSecurityRestriction(Principal $actingPrincipal, Principal $target): bool
    {
        return $actingPrincipal->id !== $target->id
            && $this->authorizeRbac(
                principal: $actingPrincipal,
                permissionCode: PermissionRegistry::IDENTITY_SECURITY_TRANSITION,
                requiresElevatedAssurance: true,
            );
    }
}
