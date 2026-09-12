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
 *   3. Concrete Scope Target lock (skipped here — IMP-003 has no concrete
 *      scope target resolver of its own besides OWN, which requires no
 *      target row lock)
 *   4. Existing Assignment / active-slot inspection
 *   5. Mutation
 *   6. Commit
 *
 * and the absolute Self-Escalation Protection rule: a Principal may never
 * grant itself a Role. There is NO exception here — the Q25 Bridge does not
 * call this service; it inserts its one bootstrap row directly with
 * assigned_by_principal_id = NULL, which this service never produces.
 */
class RoleAssignmentService
{
    /**
     * @throws \RuntimeException on self-escalation, invalid scope, or an
     *                           inactive/tombstoned grantor or target Principal.
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

            if (! $lockedGrantor->canAuthorize()) {
                throw new \RuntimeException('Grantor Principal cannot authorize (tombstoned, disabled, or unable to authenticate).');
            }

            if (! $lockedTarget->canAuthorize()) {
                throw new \RuntimeException('Target Principal cannot receive a Role assignment (tombstoned or disabled).');
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

            return PrincipalRoleAssignment::create([
                'principal_id' => $lockedTarget->id,
                'role_id' => $role->id,
                'scope_type' => $scopeType->value,
                'scope_id' => $scopeId,
                'starts_at' => $startsAt ?? $now,
                'ends_at' => $endsAt,
                'assigned_by_principal_id' => $lockedGrantor->id,
            ]);
        });
    }

    public function revoke(Principal $revoker, PrincipalRoleAssignment $assignment): void
    {
        DB::transaction(function () use ($revoker, $assignment) {
            $locked = PrincipalRoleAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if ($locked->revoked_at !== null) {
                return;
            }

            $locked->forceFill([
                'revoked_at' => now(),
                'revoked_by_principal_id' => $revoker->id,
            ])->save();
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
