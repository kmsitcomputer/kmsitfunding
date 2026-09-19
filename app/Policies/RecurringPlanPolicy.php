<?php

namespace App\Policies;

use App\Enums\ScopeType;
use App\Models\Donation\DonationRecurringPlan;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Policies\Concerns\AuthorizesUsingRbac;
use App\Services\Donation\DonationOwnScopeResolver;
use App\Services\Donation\RecurringPlanScopeResolver;
use App\Services\Rbac\AuthorizationContext;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\ScopeResolver;

/**
 * Gates Recurring Plan capabilities (docs/implementation/IMP-008-donation.md
 * "Authorization / RBAC", HD-IMP008-03). Default DENY on every path. Same
 * permission (donation.recurring_plan.manage) for donor self-service and
 * admin override — scope distinguishes OWN (donor) from ORGANIZATION
 * (admin), exactly as the specification requires. No new permission for
 * the override.
 */
class RecurringPlanPolicy
{
    use AuthorizesUsingRbac;

    /**
     * Donor-owned recurring-plan creation for POST /me/recurring-plans:
     * donation.recurring_plan.manage must be granted at OWN scope — an
     * ORGANIZATION-only grant MUST NOT authorize donor-owned creation.
     * Evaluated through the canonical AuthorizationEvaluator chain anchored
     * on a transient self-owned plan; GLOBAL_PLATFORM passes only where the
     * existing IMP-003 scope contract explicitly allows it (no invented
     * scope semantics). There is no admin create endpoint — creation is a
     * donor-only path, so no ORGANIZATION variant exists.
     */
    public function create(Principal $actingPrincipal): bool
    {
        $anchor = (new DonationRecurringPlan)->forceFill(['donor_principal_id' => $actingPrincipal->id]);

        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE,
            resource: $anchor,
            scopeResolver: new DonationOwnScopeResolver,
            requestedScopeType: ScopeType::Own,
            requestedScopeId: null,
            ownershipCheck: fn (DonationRecurringPlan $plan, Principal $principal): bool => $plan->donor_principal_id !== null
                && $plan->donor_principal_id === $principal->id,
        );
    }

    /**
     * Donor OWN-list authorization for GET /me/recurring-plans: the caller
     * must hold donation.recurring_plan.manage at OWN scope, evaluated
     * through the canonical chain anchored on a transient self-owned plan —
     * the ownership-filtered query is NOT the authorization decision.
     */
    public function viewOwnList(Principal $actingPrincipal): bool
    {
        $anchor = (new DonationRecurringPlan)->forceFill(['donor_principal_id' => $actingPrincipal->id]);

        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE,
            resource: $anchor,
            scopeResolver: new DonationOwnScopeResolver,
            requestedScopeType: ScopeType::Own,
            requestedScopeId: null,
            ownershipCheck: fn (DonationRecurringPlan $plan, Principal $principal): bool => $plan->donor_principal_id !== null
                && $plan->donor_principal_id === $principal->id,
        );
    }

    public function viewOwn(Principal $actingPrincipal, DonationRecurringPlan $plan): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE,
            resource: $plan,
            scopeResolver: new DonationOwnScopeResolver,
            requestedScopeType: ScopeType::Own,
            requestedScopeId: null,
            ownershipCheck: fn (DonationRecurringPlan $plan, Principal $principal): bool => $plan->donor_principal_id === $principal->id,
        );
    }

    public function pauseOwn(Principal $actingPrincipal, DonationRecurringPlan $plan): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE,
            resource: $plan,
            scopeResolver: new DonationOwnScopeResolver,
            requestedScopeType: ScopeType::Own,
            requestedScopeId: null,
            ownershipCheck: fn (DonationRecurringPlan $plan, Principal $principal): bool => $plan->donor_principal_id === $principal->id,
            resourceStatePredicate: fn (DonationRecurringPlan $plan): bool => $plan->status === 'ACTIVE',
        );
    }

    public function resumeOwn(Principal $actingPrincipal, DonationRecurringPlan $plan): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE,
            resource: $plan,
            scopeResolver: new DonationOwnScopeResolver,
            requestedScopeType: ScopeType::Own,
            requestedScopeId: null,
            ownershipCheck: fn (DonationRecurringPlan $plan, Principal $principal): bool => $plan->donor_principal_id === $principal->id,
            resourceStatePredicate: fn (DonationRecurringPlan $plan): bool => $plan->status === 'PAUSED',
        );
    }

    public function cancelOwn(Principal $actingPrincipal, DonationRecurringPlan $plan): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE,
            resource: $plan,
            scopeResolver: new DonationOwnScopeResolver,
            requestedScopeType: ScopeType::Own,
            requestedScopeId: null,
            ownershipCheck: fn (DonationRecurringPlan $plan, Principal $principal): bool => $plan->donor_principal_id === $principal->id,
            resourceStatePredicate: fn (DonationRecurringPlan $plan): bool => in_array($plan->status, ['ACTIVE', 'PAUSED'], true),
        );
    }

    public function pauseAny(Principal $actingPrincipal, DonationRecurringPlan $plan): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE,
            resource: $plan,
            scopeResolver: new RecurringPlanScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (DonationRecurringPlan $plan): bool => $plan->status === 'ACTIVE',
        );
    }

    public function resumeAny(Principal $actingPrincipal, DonationRecurringPlan $plan): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE,
            resource: $plan,
            scopeResolver: new RecurringPlanScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (DonationRecurringPlan $plan): bool => $plan->status === 'PAUSED',
        );
    }

    public function cancelAny(Principal $actingPrincipal, DonationRecurringPlan $plan): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE,
            resource: $plan,
            scopeResolver: new RecurringPlanScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (DonationRecurringPlan $plan): bool => in_array($plan->status, ['ACTIVE', 'PAUSED'], true),
        );
    }

    public function viewAny(Principal $actingPrincipal): bool
    {
        return $this->hasOrganizationScope(
            $actingPrincipal,
            PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE,
            new RecurringPlanScopeResolver,
        );
    }

    /**
     * Bulk/list authorization for the admin/staff path: the caller must
     * hold the permission at ORGANIZATION scope via the established
     * domain scope resolver — an OWN-only grant never authorizes
     * cross-donor plan visibility. Default DENY on every failure.
     */
    private function hasOrganizationScope(
        Principal $actingPrincipal,
        string $permissionCode,
        ScopeResolver $scopeResolver,
    ): bool {
        if (! $actingPrincipal->canAuthorize()) {
            return false;
        }

        if (! Permission::where('code', $permissionCode)->whereNull('deprecated_at')->exists()) {
            return false;
        }

        $assignments = (new AuthorizationContext($actingPrincipal))->activeRoleAssignmentsGranting($permissionCode);

        if ($assignments->isEmpty()) {
            return false;
        }

        return $assignments->contains(function (PrincipalRoleAssignment $assignment) use ($scopeResolver) {
            if ($assignment->scope_type === ScopeType::GlobalPlatform) {
                return true;
            }

            return $assignment->scope_type === ScopeType::Organization
                && $scopeResolver->scopeType() === ScopeType::Organization;
        });
    }
}
