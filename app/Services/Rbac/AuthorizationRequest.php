<?php

namespace App\Services\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\AuthorityType;
use App\Models\Rbac\Principal;

/**
 * The Canonical Authorization Context (DATA-SCOPE-MODEL.md) fed into
 * AuthorizationEvaluator::evaluate() — one value object per request/check,
 * never persisted, never cached across requests (see "Cache").
 *
 * Every field beyond principal/permissionCode is nullable/optional because
 * not every capability requires a Scope, Ownership rule, Business
 * Authority, Resource State predicate, or Approval predicate — but every
 * field that IS supplied is evaluated as a mandatory AND-term (§9 "Required
 * Business Authority" etc. are never trivially satisfied once applicable).
 */
final class AuthorizationRequest
{
    /**
     * @param  mixed  $resource  the concrete Target Resource (Step 0) — null only for
     *                           capabilities with no single target resource (e.g. a bulk/administrative action);
     *                           a null resource where the caller nonetheless supplies a ScopeResolver is treated
     *                           as an unresolved Target Resource and denies at Step 0.
     * @param  (\Closure(mixed $resource, Principal $principal): bool)|null  $ownershipCheck
     * @param  (\Closure(mixed $resource): bool)|null  $resourceStatePredicate  Step 7 — owning
     *                                                                          domain supplies; null means this capability has no resource-state precondition.
     * @param  (\Closure(): bool)|null  $approvalPredicate  Step 8 — owning domain supplies
     *                                                      (typically backed by ApprovalCandidateResolver); null means no approval-state gate applies.
     */
    public function __construct(
        public readonly Principal $principal,
        public readonly string $permissionCode,
        public readonly mixed $resource = null,
        public readonly ?ScopeResolver $scopeResolver = null,
        public readonly ?ScopeType $requestedScopeType = null,
        public readonly ?int $requestedScopeId = null,
        public readonly ?\Closure $ownershipCheck = null,
        public readonly ?AuthorityType $requiredAuthorityType = null,
        public readonly ?\Closure $resourceStatePredicate = null,
        public readonly ?\Closure $approvalPredicate = null,
        public readonly bool $requiresElevatedAssurance = false,
    ) {}
}
