<?php

namespace App\Policies\Concerns;

use App\Enums\ScopeType;
use App\Models\Rbac\AuthorityType;
use App\Models\Rbac\Principal;
use App\Services\Rbac\ApprovalCandidateResolver;
use App\Services\Rbac\AuthorizationEvaluator;
use App\Services\Rbac\AuthorizationRequest;
use App\Services\Rbac\ScopeResolver;

/**
 * Shared AND-chain sequencing for domain Policies (see "Policy / Gate
 * Integration" — "a shared AuthorizesRequest-style trait/base Policy class
 * implements the common AND-chain sequencing so each domain Policy only
 * supplies its OWN permission code, scope resolver, ownership check, and
 * resource-state predicate — not re-implementing the sequencing").
 *
 * Every Policy method's default is DENY: this trait never returns true
 * except through AuthorizationEvaluator's own explicit ALLOW path.
 */
trait AuthorizesUsingRbac
{
    protected function authorizeRbac(
        Principal $principal,
        string $permissionCode,
        mixed $resource = null,
        ?ScopeResolver $scopeResolver = null,
        ?ScopeType $requestedScopeType = null,
        ?int $requestedScopeId = null,
        ?\Closure $ownershipCheck = null,
        ?AuthorityType $requiredAuthorityType = null,
        ?\Closure $resourceStatePredicate = null,
        ?ApprovalCandidateResolver $approvalResolver = null,
        mixed $approvalStep = null,
        bool $requiresElevatedAssurance = false,
    ): bool {
        $approvalPredicate = $approvalResolver === null
            ? null
            : fn () => $approvalResolver->isApprovalCandidate($principal, $approvalStep, $resource);

        $request = new AuthorizationRequest(
            principal: $principal,
            permissionCode: $permissionCode,
            resource: $resource,
            scopeResolver: $scopeResolver,
            requestedScopeType: $requestedScopeType,
            requestedScopeId: $requestedScopeId,
            ownershipCheck: $ownershipCheck,
            requiredAuthorityType: $requiredAuthorityType,
            resourceStatePredicate: $resourceStatePredicate,
            approvalPredicate: $approvalPredicate,
            requiresElevatedAssurance: $requiresElevatedAssurance,
        );

        return app(AuthorizationEvaluator::class)->evaluate($request);
    }
}
