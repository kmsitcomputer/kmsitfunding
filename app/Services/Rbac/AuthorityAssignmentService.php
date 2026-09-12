<?php

namespace App\Services\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\AuthorityAssignment;
use App\Models\Rbac\AuthorityType;
use App\Models\Rbac\Principal;
use Illuminate\Support\Facades\DB;

/**
 * Principal -> Authority Type assignment/revocation (financial and
 * non-financial authority primitives). Same stable lock order and renewal
 * pattern as RoleAssignmentService; Authority is a distinct primitive from
 * Role and is never inferred from Role, Permission, or Scope.
 *
 * assigned_by_principal_id is NOT nullable for this table (unlike
 * principal_role_assignments, which the Q25 Bridge populates with a NULL
 * grantor for exactly one bootstrap row) — no authority assignment is ever
 * created without a concrete grantor Principal.
 */
class AuthorityAssignmentService
{
    public function assign(
        Principal $grantor,
        Principal $target,
        AuthorityType $authorityType,
        ScopeType $scopeType,
        ?int $scopeId,
        ?\DateTimeInterface $startsAt = null,
        ?\DateTimeInterface $endsAt = null,
    ): AuthorityAssignment {
        $this->assertScopeIdMatchesType($scopeType, $scopeId);

        if ($grantor->id === $target->id) {
            throw new \RuntimeException('Self Authority Grant is denied (Self-Escalation Protection) — applies unconditionally, including Financial Authority.');
        }

        return DB::transaction(function () use ($grantor, $target, $authorityType, $scopeType, $scopeId, $startsAt, $endsAt) {
            [$first, $second] = $grantor->id < $target->id ? [$grantor, $target] : [$target, $grantor];

            $lockedFirst = Principal::whereKey($first->id)->lockForUpdate()->firstOrFail();
            $lockedSecond = Principal::whereKey($second->id)->lockForUpdate()->firstOrFail();

            $lockedGrantor = $lockedFirst->id === $grantor->id ? $lockedFirst : $lockedSecond;
            $lockedTarget = $lockedFirst->id === $target->id ? $lockedFirst : $lockedSecond;

            if (! $lockedGrantor->canAuthorize()) {
                throw new \RuntimeException('Grantor Principal cannot authorize (tombstoned, disabled, or unable to authenticate).');
            }

            if (! $lockedTarget->canAuthorize()) {
                throw new \RuntimeException('Target Principal cannot receive an Authority assignment (tombstoned or disabled).');
            }

            $now = now();

            AuthorityAssignment::where('principal_id', $lockedTarget->id)
                ->where('authority_type_id', $authorityType->id)
                ->where('scope_type', $scopeType->value)
                ->where(function ($query) use ($scopeId) {
                    $scopeId === null ? $query->whereNull('scope_id') : $query->where('scope_id', $scopeId);
                })
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $now,
                    'revoked_by_principal_id' => $lockedGrantor->id,
                ]);

            return AuthorityAssignment::create([
                'principal_id' => $lockedTarget->id,
                'authority_type_id' => $authorityType->id,
                'scope_type' => $scopeType->value,
                'scope_id' => $scopeId,
                'starts_at' => $startsAt ?? $now,
                'ends_at' => $endsAt,
                'assigned_by_principal_id' => $lockedGrantor->id,
            ]);
        });
    }

    public function revoke(Principal $revoker, AuthorityAssignment $assignment): void
    {
        DB::transaction(function () use ($revoker, $assignment) {
            $locked = AuthorityAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();

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
