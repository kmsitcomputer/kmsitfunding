<?php

namespace App\Services\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use Illuminate\Database\Eloquent\Builder;

/**
 * IMP-003 domain-aware Scope Resolver contract (see "Domain-Aware Scope
 * Resolution"). One implementation per domain/scope type. IMP-003 itself
 * implements only OwnUserScopeResolver, below — every other resolver
 * (Partner, Campaign, Fundraiser, Beneficiary Case, etc.) is implemented BY
 * that domain's own later stage, against this same contract.
 *
 * The specification illustrates this contract against an "assignment"
 * object; this implementation instead passes the resolved Principal and
 * scope_id directly (an ENGINEERING CHOICE — simpler, and does not require
 * inventing a separate ScopeAssignment value type the specification does
 * not otherwise define).
 */
interface ScopeResolver
{
    public function scopeType(): ScopeType;

    /**
     * Whether $resource falls within the scope this Principal holds via
     * $scopeId (null for scope types with no concrete target row).
     */
    public function resourceMatchesScope(mixed $resource, Principal $principal, ?int $scopeId): bool;

    /**
     * Constrain a list/search/report query to only rows within this
     * Principal's scope — applied BEFORE the query executes (§27
     * "Query-Level Enforcement" — never "fetch all, then filter").
     */
    public function applyToQuery(Builder $query, Principal $principal, ?int $scopeId): Builder;

    /**
     * WRITE-TIME target validation ("Polymorphic Scope Target Locking"):
     * resolve the concrete scope target row for $scopeId and lock it
     * (e.g. `->lockForUpdate()->first()`) WITHIN THE CALLER'S OPEN
     * TRANSACTION, then return it only if it exists and is still
     * active/assignable — null otherwise (target missing, or
     * deleted/deactivated). The caller (RoleAssignmentService /
     * AuthorityAssignmentService) treats a null return as REJECT.
     *
     * A future domain's own delete/deactivate path for this scope type's
     * target table MUST lock the identical row before deleting/
     * deactivating it, so the two paths serialize against each other and
     * neither can produce an orphaned active assignment — this method is
     * that shared lock point.
     */
    public function lockAndValidateTarget(int $scopeId): ?object;
}
