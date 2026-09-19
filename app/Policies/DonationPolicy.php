<?php

namespace App\Policies;

use App\Enums\ScopeType;
use App\Models\Donation\Donation;
use App\Models\Rbac\Principal;
use App\Policies\Concerns\AuthorizesUsingRbac;
use App\Services\Donation\DonationOwnScopeResolver;
use App\Services\Donation\DonationScopeResolver;
use App\Services\Rbac\PermissionRegistry;

/**
 * Gates Donation capabilities (docs/implementation/IMP-008-donation.md
 * "Authorization / RBAC"). Default DENY on every path.
 *
 * viewOwn: donor reads their own Donations — donation.view AND scope OWN
 *   (ownership rule: donor_principal_id == acting Principal).
 * viewAny/viewDonation: admin/staff reads across donors — donation.view
 *   AND scope ORGANIZATION.
 * cancelOwn: authenticated donor cancels their own PENDING Donation —
 *   donation.cancel AND scope OWN AND state PENDING. There is deliberately
 *   NO guest self-service path (HD-IMP008-05B): a NULL donor_principal_id
 *   row never satisfies the ownership rule, and no guest-facing endpoint
 *   exists — guest cancellation is the admin support path below.
 * cancelAny: admin/support cancels any PENDING Donation (including guest
 *   rows) — donation.cancel AND scope ORGANIZATION AND state PENDING.
 * markSucceeded/markFailed/markExpired: NO human-facing permission can
 *   trigger these — reserved for an authorized System Principal
 *   invocation (IMP-009/011 define the caller). This method asserts the
 *   caller's Principal is a live System kind; business authority for the
 *   specific consequence surface is the caller's own concern.
 */
class DonationPolicy
{
    use AuthorizesUsingRbac;

    public function viewOwn(Principal $actingPrincipal, Donation $donation): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_VIEW,
            resource: $donation,
            scopeResolver: new DonationOwnScopeResolver,
            requestedScopeType: ScopeType::Own,
            requestedScopeId: null,
            ownershipCheck: fn (Donation $donation, Principal $principal): bool => $donation->donor_principal_id !== null
                && $donation->donor_principal_id === $principal->id,
        );
    }

    public function viewAny(Principal $actingPrincipal): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_VIEW,
        );
    }

    public function viewDonation(Principal $actingPrincipal, Donation $donation): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_VIEW,
            resource: $donation,
            scopeResolver: new DonationScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }

    public function cancelOwn(Principal $actingPrincipal, Donation $donation): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_CANCEL,
            resource: $donation,
            scopeResolver: new DonationOwnScopeResolver,
            requestedScopeType: ScopeType::Own,
            requestedScopeId: null,
            ownershipCheck: fn (Donation $donation, Principal $principal): bool => $donation->donor_principal_id !== null
                && $donation->donor_principal_id === $principal->id,
            resourceStatePredicate: fn (Donation $donation): bool => $donation->status === 'PENDING',
        );
    }

    public function cancelAny(Principal $actingPrincipal, Donation $donation): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::DONATION_CANCEL,
            resource: $donation,
            scopeResolver: new DonationScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (Donation $donation): bool => $donation->status === 'PENDING',
        );
    }

    public function markSucceeded(Principal $actingPrincipal, Donation $donation): bool
    {
        return $this->authorizeSystemConsequence($actingPrincipal, $donation);
    }

    public function markFailed(Principal $actingPrincipal, Donation $donation): bool
    {
        return $this->authorizeSystemConsequence($actingPrincipal, $donation);
    }

    public function markExpired(Principal $actingPrincipal, Donation $donation): bool
    {
        return $this->authorizeSystemConsequence($actingPrincipal, $donation);
    }

    private function authorizeSystemConsequence(Principal $actingPrincipal, Donation $donation): bool
    {
        if ($donation->status !== 'PENDING') {
            return false;
        }

        if ($actingPrincipal->principal_kind->value !== 'system') {
            return false;
        }

        return $actingPrincipal->canAuthorize();
    }
}
