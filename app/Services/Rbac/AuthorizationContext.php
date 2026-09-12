<?php

namespace App\Services\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\AuthorityAssignment;
use App\Models\Rbac\AuthorityType;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use Illuminate\Support\Collection;

/**
 * Resolves a Principal's currently-active Role/Permission/Scope/Authority
 * Assignments into a lightweight, request-scoped read model. NOT cached
 * across requests by default (see "Cache") — a fresh instance per
 * AuthorizationEvaluator::evaluate() call, always reading current DB state.
 */
class AuthorizationContext
{
    /** @var Collection<int, PrincipalRoleAssignment>|null */
    private ?Collection $activeRoleAssignments = null;

    /** @var Collection<int, AuthorityAssignment>|null */
    private ?Collection $activeAuthorityAssignments = null;

    public function __construct(private readonly Principal $principal) {}

    /** @return Collection<int, PrincipalRoleAssignment> */
    private function activeRoleAssignments(): Collection
    {
        return $this->activeRoleAssignments ??= PrincipalRoleAssignment::query()
            ->where('principal_id', $this->principal->id)
            ->whereNull('revoked_at')
            ->where('starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->with('role.permissions')
            ->get();
    }

    /** @return Collection<int, AuthorityAssignment> */
    private function activeAuthorityAssignments(): Collection
    {
        return $this->activeAuthorityAssignments ??= AuthorityAssignment::query()
            ->where('principal_id', $this->principal->id)
            ->whereNull('revoked_at')
            ->where('starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->get();
    }

    /**
     * Every active Role assignment (with its scope) that grants
     * $permissionCode — the caller then checks whether any of these
     * assignments' scope covers the requested Target Resource.
     *
     * @return Collection<int, PrincipalRoleAssignment>
     */
    public function activeRoleAssignmentsGranting(string $permissionCode): Collection
    {
        return $this->activeRoleAssignments()
            ->filter(fn (PrincipalRoleAssignment $assignment) => $assignment->role->permissions
                ->contains(fn ($permission) => $permission->code === $permissionCode));
    }

    public function hasApplicableAuthority(AuthorityType $authorityType, ScopeType $requestedScopeType, ?int $requestedScopeId): bool
    {
        return $this->activeAuthorityAssignments()
            ->filter(fn (AuthorityAssignment $assignment) => $assignment->authority_type_id === $authorityType->id)
            ->contains(fn (AuthorityAssignment $assignment) => ScopeContainment::covers(
                $assignment->scope_type,
                $assignment->scope_id,
                $requestedScopeType,
                $requestedScopeId,
            ));
    }
}
