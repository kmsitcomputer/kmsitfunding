<?php

namespace App\Policies;

use App\Enums\ScopeType;
use App\Models\Donation\Donation;
use App\Models\Payment\Payment;
use App\Models\Rbac\AuthorityType;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Policies\Concerns\AuthorizesUsingRbac;
use App\Services\Payment\PaymentOwnScopeResolver;
use App\Services\Payment\PaymentScopeResolver;
use App\Services\Rbac\AuthorizationContext;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\ScopeResolver;

/**
 * Gates Payment capabilities (docs/implementation/IMP-009-payment-hub.md
 * "Authorization"). Default DENY on every path.
 *
 * createOwn: authenticated donor creates against their own PENDING
 *   Donation — payment.create AND scope OWN (ownership rule: owning
 *   Donation's donor_principal_id == acting Principal) AND resource
 *   state PENDING. Guest creation has no Policy path (no Principal) —
 *   the service gates it on Donation PENDING + HD-IMP009-01 + the
 *   idempotency key, identically to the authenticated contract minus
 *   ownership.
 * viewOwn/viewPayment: donor reads their own Payments (OWN);
 *   admin/staff reads across donors (ORGANIZATION).
 * cancelOwn/cancelAny: donor cancels their own PENDING/REQUIRES_ACTION
 *   Payment (OWN); admin cancels any (ORGANIZATION, also the authorized
 *   support-controlled process for abandoned guest attempts,
 *   HD-IMP009-04). Guest self-service cancellation is NOT supported.
 * submitEvidence: donor/guest submits proof for their own Payment —
 *   payment.manual_transfer.submit_evidence, same ownership shape as
 *   creation. The guest path (no Principal) is service-gated, not a
 *   Policy path.
 * verifyManualTransfer: admin approve/reject/hold —
 *   payment.manual_transfer.verify AND scope ORGANIZATION AND Business
 *   Authority financial_approver (the closest fit in the CLOSED
 *   taxonomy — no new Authority Type invented) AND resource state
 *   PENDING. The AMOUNT_MISMATCH_HOLD outcome uses the identical gate.
 * manageProviderConfig: GLOBAL_PLATFORM scope — a provider is a
 *   platform-wide concern. Super Admin does NOT automatically imply
 *   this authority — an explicit permission + scope grant is required
 *   regardless of Super Admin status.
 * System/provider-outcome transitions (SUCCEEDED/FAILED/EXPIRED via
 * webhook/poll/sweep): NO human-facing permission can trigger these —
 * reserved for an Integration/System Principal invocation. This method
 * asserts the caller's Principal is a live non-human kind; business
 * authority for the specific consequence surface is the caller's own
 * concern.
 */
class PaymentPolicy
{
    use AuthorizesUsingRbac;

    private function ownPayment(Principal $actingPrincipal, Payment $payment): bool
    {
        $donorPrincipalId = $payment->relationLoaded('donation')
            ? $payment->donation?->donor_principal_id
            : $payment->donation()->first()?->donor_principal_id;

        return $donorPrincipalId !== null && $donorPrincipalId === $actingPrincipal->id;
    }

    public function createOwn(Principal $actingPrincipal, Payment $payment): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PAYMENT_CREATE,
            resource: $payment,
            scopeResolver: new PaymentOwnScopeResolver,
            requestedScopeType: ScopeType::Own,
            requestedScopeId: null,
            ownershipCheck: fn (Payment $payment, Principal $principal): bool => $this->ownPayment($principal, $payment),
            resourceStatePredicate: fn (Payment $payment): bool => $payment->donation()->first()?->status === 'PENDING',
        );
    }

    public function viewOwn(Principal $actingPrincipal, Payment $payment): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PAYMENT_VIEW,
            resource: $payment,
            scopeResolver: new PaymentOwnScopeResolver,
            requestedScopeType: ScopeType::Own,
            requestedScopeId: null,
            ownershipCheck: fn (Payment $payment, Principal $principal): bool => $this->ownPayment($principal, $payment),
        );
    }

    public function viewOwnList(Principal $actingPrincipal): bool
    {
        // F-10: OWN listing uses the OWN resolver (donation ownership
        // correlation), never the Organization resolver — requesting
        // OWN while resolving Organization would admit
        // Organization-only grants into an OWN capability. Mirrors
        // DonationPolicy::viewOwnList: a transient self-owned anchor
        // (a Payment whose owning Donation belongs to the acting
        // Principal) plus the ownership check, so the evaluator's
        // scope term is satisfied through the OWN resolver only.
        $anchorDonation = (new Donation)->forceFill([
            'donor_principal_id' => $actingPrincipal->id,
        ]);
        $anchor = (new Payment)->forceFill(['donation_id' => -1]);
        $anchor->setRelation('donation', $anchorDonation);

        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PAYMENT_VIEW,
            resource: $anchor,
            scopeResolver: new PaymentOwnScopeResolver,
            requestedScopeType: ScopeType::Own,
            requestedScopeId: null,
            ownershipCheck: fn (Payment $payment, Principal $principal): bool => $this->ownPayment($principal, $payment),
        );
    }

    public function viewAny(Principal $actingPrincipal): bool
    {
        return $this->hasOrganizationScope(
            $actingPrincipal,
            PermissionRegistry::PAYMENT_VIEW,
            new PaymentScopeResolver,
        );
    }

    public function viewPayment(Principal $actingPrincipal, Payment $payment): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PAYMENT_VIEW,
            resource: $payment,
            scopeResolver: new PaymentScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
        );
    }

    public function cancelOwn(Principal $actingPrincipal, Payment $payment): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PAYMENT_CANCEL,
            resource: $payment,
            scopeResolver: new PaymentOwnScopeResolver,
            requestedScopeType: ScopeType::Own,
            requestedScopeId: null,
            ownershipCheck: fn (Payment $payment, Principal $principal): bool => $this->ownPayment($principal, $payment),
            resourceStatePredicate: fn (Payment $payment): bool => in_array($payment->status, ['PENDING', 'REQUIRES_ACTION'], true),
        );
    }

    public function cancelAny(Principal $actingPrincipal, Payment $payment): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PAYMENT_CANCEL,
            resource: $payment,
            scopeResolver: new PaymentScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            resourceStatePredicate: fn (Payment $payment): bool => in_array($payment->status, ['PENDING', 'REQUIRES_ACTION'], true),
        );
    }

    public function submitEvidence(Principal $actingPrincipal, Payment $payment): bool
    {
        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PAYMENT_MANUAL_TRANSFER_SUBMIT_EVIDENCE,
            resource: $payment,
            scopeResolver: new PaymentOwnScopeResolver,
            requestedScopeType: ScopeType::Own,
            requestedScopeId: null,
            ownershipCheck: fn (Payment $payment, Principal $principal): bool => $this->ownPayment($principal, $payment),
        );
    }

    public function verifyManualTransfer(Principal $actingPrincipal, Payment $payment): bool
    {
        $authorityType = AuthorityType::where('code', AuthorityType::FINANCIAL_APPROVER)->first();

        if ($authorityType === null) {
            return false;
        }

        return $this->authorizeRbac(
            principal: $actingPrincipal,
            permissionCode: PermissionRegistry::PAYMENT_MANUAL_TRANSFER_VERIFY,
            resource: $payment,
            scopeResolver: new PaymentScopeResolver,
            requestedScopeType: ScopeType::Organization,
            requestedScopeId: null,
            requiredAuthorityType: $authorityType,
            resourceStatePredicate: fn (Payment $payment): bool => $payment->status === 'PENDING',
        );
    }

    public function manageProviderConfig(Principal $actingPrincipal): bool
    {
        // GLOBAL_PLATFORM scope with no single target resource: the
        // evaluator's scope term needs an explicit assignment-scope
        // check here (no resolver applies to a resourceless
        // capability). Only a GlobalPlatform-held grant covers a
        // GlobalPlatform-requested scope (ScopeContainment) — an
        // ORGANIZATION grant never authorizes platform-wide provider
        // configuration. Default DENY on every failure.
        if (! $actingPrincipal->canAuthorize()) {
            return false;
        }

        if (! Permission::where('code', PermissionRegistry::PAYMENT_PROVIDER_CONFIG_MANAGE)->whereNull('deprecated_at')->exists()) {
            return false;
        }

        $assignments = (new AuthorizationContext($actingPrincipal))
            ->activeRoleAssignmentsGranting(PermissionRegistry::PAYMENT_PROVIDER_CONFIG_MANAGE);

        return $assignments->contains(
            fn (PrincipalRoleAssignment $assignment) => $assignment->scope_type === ScopeType::GlobalPlatform
        );
    }

    public function transitionAsSystem(Principal $actingPrincipal, Payment $payment): bool
    {
        if (in_array($actingPrincipal->principal_kind->value, ['human'], true)) {
            return false;
        }

        return $actingPrincipal->canAuthorize();
    }

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
