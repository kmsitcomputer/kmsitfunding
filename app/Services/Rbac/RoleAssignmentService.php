<?php

namespace App\Services\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use Illuminate\Support\Facades\DB;

/**
 * Principal -> Role assignment/revocation. Enforces, in this exact order,
 * the specification's stable lock order ("Concurrency"):
 *   1. Grantor Principal lock
 *   2. Target Principal lock (ascending principal_id if different row)
 *   3. Concrete Scope Target lock — via the registered `ScopeResolver` for
 *      $scopeType (`IMP003-IMPL-M02`); skipped only for scope types with no
 *      concrete target row (`ScopeType::requiresNullScopeId()`)
 *   4. Existing Assignment / active-slot inspection
 *   5. Mutation
 *   6. Commit
 *
 * and the absolute Self-Escalation Protection rule: a Principal may never
 * grant itself a Role. There is NO exception here — the Q25 Bridge does not
 * call this service; it inserts its one bootstrap row directly with
 * assigned_by_principal_id = NULL, which this service never produces.
 *
 * `IMP003-IMPL-M01`: both assignment AND revocation require the acting
 * Principal to be locked, re-validated, and to hold the operation's
 * required `rbac.role.assign`/`rbac.role.revoke` permission under ELEVATED
 * assurance — enforced here, at the authoritative service layer, never left
 * to a caller/controller/route.
 */
class RoleAssignmentService
{
    public function __construct(
        private readonly RbacMutationGuard $guard,
        private readonly ScopeResolverRegistry $scopeResolvers,
        private readonly RbacAuditLogger $audit,
    ) {}

    /**
     * @throws \RuntimeException on self-escalation, invalid/unresolvable
     *                           scope, unauthorized actor, or an inactive/tombstoned grantor or
     *                           target Principal.
     */
    public function assign(
        Principal $grantor,
        Principal $target,
        Role $role,
        ScopeType $scopeType,
        ?int $scopeId,
        ?\DateTimeInterface $startsAt = null,
        ?\DateTimeInterface $endsAt = null,
    ): PrincipalRoleAssignment {
        $this->assertScopeIdMatchesType($scopeType, $scopeId);

        if ($grantor->id === $target->id) {
            throw new \RuntimeException('Self Role Assignment is denied (Self-Escalation Protection).');
        }

        return DB::transaction(function () use ($grantor, $target, $role, $scopeType, $scopeId, $startsAt, $endsAt) {
            [$first, $second] = $grantor->id < $target->id ? [$grantor, $target] : [$target, $grantor];

            $lockedFirst = Principal::whereKey($first->id)->lockForUpdate()->firstOrFail();
            $lockedSecond = Principal::whereKey($second->id)->lockForUpdate()->firstOrFail();

            $lockedGrantor = $lockedFirst->id === $grantor->id ? $lockedFirst : $lockedSecond;
            $lockedTarget = $lockedFirst->id === $target->id ? $lockedFirst : $lockedSecond;

            $this->guard->ensureAuthorized($lockedGrantor, PermissionRegistry::RBAC_ROLE_ASSIGN);

            if (! $lockedTarget->canAuthorize()) {
                throw new \RuntimeException('Target Principal cannot receive a Role assignment (tombstoned or disabled).');
            }

            // Step 3: Concrete Scope Target lock — fail-closed if no
            // resolver is registered for a scope type that requires one.
            if (! $scopeType->requiresNullScopeId()) {
                $resolver = $this->scopeResolvers->get($scopeType);

                if ($resolver === null) {
                    throw new \RuntimeException(
                        "No registered ScopeResolver for concrete scope type {$scopeType->value} — ".
                        'assignment rejected (fail-closed; that domain does not exist yet).'
                    );
                }

                if ($resolver->lockAndValidateTarget($scopeId) === null) {
                    throw new \RuntimeException(
                        "Scope target {$scopeId} for {$scopeType->value} does not exist or is not active/assignable."
                    );
                }
            }

            $now = now();

            // Renewal/replacement pattern: revoke any currently-active
            // identical (principal, role, scope_type, scope_id) slot before
            // inserting the new one, inside this same transaction, so the
            // generated active_assignment_key unique index never collides.
            PrincipalRoleAssignment::where('principal_id', $lockedTarget->id)
                ->where('role_id', $role->id)
                ->where('scope_type', $scopeType->value)
                ->where(function ($query) use ($scopeId) {
                    $scopeId === null ? $query->whereNull('scope_id') : $query->where('scope_id', $scopeId);
                })
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $now,
                    'revoked_by_principal_id' => $lockedGrantor->id,
                ]);

            $assignment = PrincipalRoleAssignment::create([
                'principal_id' => $lockedTarget->id,
                'role_id' => $role->id,
                'scope_type' => $scopeType->value,
                'scope_id' => $scopeId,
                'starts_at' => $startsAt ?? $now,
                'ends_at' => $endsAt,
                'assigned_by_principal_id' => $lockedGrantor->id,
            ]);

            $this->audit->record('role_assigned', [
                'grantor_principal_id' => $lockedGrantor->id,
                'assignment_id' => $assignment->id,
                'target_principal_id' => $lockedTarget->id,
                'role_id' => $role->id,
                'scope_type' => $scopeType->value,
                'scope_id' => $scopeId,
            ]);

            return $assignment;
        });
    }

    public function revoke(Principal $revoker, PrincipalRoleAssignment $assignment): void
    {
        DB::transaction(function () use ($revoker, $assignment) {
            $lockedRevoker = Principal::whereKey($revoker->id)->lockForUpdate()->firstOrFail();

            $this->guard->ensureAuthorized($lockedRevoker, PermissionRegistry::RBAC_ROLE_REVOKE);

            $locked = PrincipalRoleAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->revoked_at !== null) {
                return;
            }

            $locked->forceFill([
                'revoked_at' => now(),
                'revoked_by_principal_id' => $lockedRevoker->id,
            ])->save();

            $this->audit->record('role_revoked', [
                'revoker_principal_id' => $lockedRevoker->id,
                'assignment_id' => $locked->id,
                'principal_id' => $locked->principal_id,
                'role_id' => $locked->role_id,
            ]);
        });
    }

    private function assertScopeIdMatchesType(ScopeType $scopeType, ?int $scopeId): void
    {
        if ($scopeType->requiresNullScopeId() && $scopeId !== null) {
            throw new \RuntimeException("Scope type {$scopeType->value} requires a null scope_id.");
        }

        if (! $scopeType->requiresNullScopeId() && $scopeId === null) {
            throw new \RuntimeException("Scope type {$scopeType->value} requires a concrete scope_id.");
        }
    }
}
