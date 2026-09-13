<?php

namespace App\Services\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\Permission;
use App\Services\Identity\AssuranceService;

/**
 * The canonical Step-0-plus-9-term AND-chain ("Canonical Authorization
 * Formula (implementation form)"), short-circuiting DENY at the first
 * failing term. This is the ONE place the sequence is implemented — Policies
 * call this, never re-implement the ordering themselves (see "Policy / Gate
 * Integration").
 *
 * Steps 7 (Resource State) and 8 (Approval State) are owning-domain
 * predicates supplied via AuthorizationRequest; when a capability has none,
 * the corresponding field is null and that step is trivially satisfied —
 * this is NOT the same as "Permission trivially satisfied" (forbidden by
 * IMP003-REAUDIT-M02): those two steps are explicitly documented as
 * domain-owned and contract-only for IMP-003, whereas Permission (step 3)
 * always has a concrete registered-code check with no null bypass.
 */
class AuthorizationEvaluator
{
    public function __construct(private readonly AssuranceService $assurance) {}

    public function evaluate(AuthorizationRequest $request): bool
    {
        // Step 0: Applicable Subject Context Resolution.
        if (! $this->principalResolved($request)) {
            return false;
        }

        if (! Permission::where('code', $request->permissionCode)->whereNull('deprecated_at')->exists()) {
            return false;
        }

        if ($request->scopeResolver !== null && $request->resource === null) {
            return false;
        }

        // Step 1: Authenticated + Step 2: No Security Restriction.
        if (! $request->principal->canAuthorize()) {
            return false;
        }

        // Step 3: Permission.
        $context = new AuthorizationContext($request->principal);
        $grantingAssignments = $context->activeRoleAssignmentsGranting($request->permissionCode);

        if ($grantingAssignments->isEmpty()) {
            return false;
        }

        // Step 4: Applicable Data Scope.
        if ($request->scopeResolver !== null) {
            $covered = $grantingAssignments->contains(function ($assignment) use ($request) {
                if ($assignment->scope_type === ScopeType::GlobalPlatform) {
                    return true;
                }

                if ($assignment->scope_type !== $request->scopeResolver->scopeType()) {
                    return false;
                }

                return $request->scopeResolver->resourceMatchesScope(
                    $request->resource,
                    $request->principal,
                    $assignment->scope_id,
                );
            });

            if (! $covered) {
                return false;
            }
        }

        // Step 5: Ownership / Subject Access Rule.
        if ($request->ownershipCheck !== null && ! ($request->ownershipCheck)($request->resource, $request->principal)) {
            return false;
        }

        // Step 6: Required Business Authority.
        if ($request->requiredAuthorityType !== null) {
            $scopeType = $request->requestedScopeType ?? $request->scopeResolver?->scopeType();

            if ($scopeType === null || ! $context->hasApplicableAuthority($request->requiredAuthorityType, $scopeType, $request->requestedScopeId)) {
                return false;
            }
        }

        // Step 7: Valid Resource State (owning domain predicate).
        if ($request->resourceStatePredicate !== null && ! ($request->resourceStatePredicate)($request->resource)) {
            return false;
        }

        // Step 8: Required Approval State (owning domain predicate).
        if ($request->approvalPredicate !== null && ! ($request->approvalPredicate)()) {
            return false;
        }

        // Step 9: Required Authentication Assurance.
        if ($request->requiresElevatedAssurance && ! $this->assurance->isElevated()) {
            return false;
        }

        return true;
    }

    private function principalResolved(AuthorizationRequest $request): bool
    {
        return $request->principal->exists && $request->principal->id !== null;
    }
}
