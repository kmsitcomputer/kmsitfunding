<?php

namespace App\Services\Audit;

use App\Enums\ScopeType;
use App\Models\Rbac\Principal;
use App\Services\Rbac\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * IMP-004 audit-read scope resolver. Every canonical audit event registered
 * by this stage is classified at GLOBAL_PLATFORM scope — an audit.read.*
 * permission held at any narrower scope (PARTNER, CAMPAIGN, OWN, ...) never
 * covers an audit record, which is what makes a cross-scope audit read fail
 * closed through the canonical AuthorizationEvaluator.
 */
final class AuditScopeResolver implements ScopeResolver
{
    public function scopeType(): ScopeType
    {
        return ScopeType::GlobalPlatform;
    }

    public function resourceMatchesScope(mixed $resource, Principal $principal, ?int $scopeId): bool
    {
        // Only a GLOBAL_PLATFORM-scoped assignment covers audit evidence, and
        // the evaluator's GlobalPlatform-covers-everything rule handles that
        // before ever calling this — anything reaching here is not covered.
        return false;
    }

    public function applyToQuery(Builder $query, Principal $principal, ?int $scopeId): Builder
    {
        return $query;
    }

    public function lockAndValidateTarget(int $scopeId): ?object
    {
        return null;
    }
}
