<?php

namespace App\Services\Rbac;

use App\Models\Rbac\Principal;

/**
 * Shared authoritative-service-layer enforcement point for every RBAC
 * management mutation (Role assignment/revocation, Authority assignment/
 * revocation, Role-Permission grant/revoke, non-human Principal
 * deactivation) — see `IMP003-IMPL-M01`. The mutation services below never
 * trust a controller/route/UI to have already checked this; each calls this
 * guard itself, on a LOCKED acting Principal, inside the same transaction
 * that performs the mutation.
 *
 * Reuses `AuthorizationEvaluator` (Codex-confirmed PASS — not reimplemented
 * here) rather than duplicating the AND-chain: every RBAC management
 * capability requires ELEVATED assurance, exactly as
 * "Authentication Assurance (integration, not redefinition)" specifies for
 * "assign role, assign permission, assign authority, revoke any of the
 * above" — this guard is that requirement's one enforcement point, not the
 * business/financial-authority path (RBAC management itself carries no
 * Business Authority requirement per the specification).
 */
class RbacMutationGuard
{
    public function __construct(private readonly AuthorizationEvaluator $evaluator) {}

    /**
     * @throws \RuntimeException when $actor lacks $permissionCode, is not
     *                           ELEVATED, is tombstoned/disabled, or is under a Security Restriction.
     */
    public function ensureAuthorized(Principal $actor, string $permissionCode): void
    {
        $allowed = $this->evaluator->evaluate(new AuthorizationRequest(
            principal: $actor,
            permissionCode: $permissionCode,
            requiresElevatedAssurance: true,
        ));

        if (! $allowed) {
            throw new \RuntimeException(
                "Principal {$actor->id} is not authorized to perform '{$permissionCode}' ".
                '(missing permission, insufficient authentication assurance, or an active security restriction).'
            );
        }
    }
}
