<?php

namespace App\Policies;

use App\Enums\ScopeType;
use App\Models\Donation\DonationRecurringPlan;
use App\Models\Rbac\Principal;
use App\Policies\Concerns\AuthorizesUsingRbac;
use App\Services\Donation\DonationOwnScopeResolver;
use App\Services\Donation\RecurringPlanScopeResolver;
use App\Services\Rbac\PermissionRegistry;

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

    public function create(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE,
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
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_RECURRING_PLAN_MANAGE,
        );
    }
}
