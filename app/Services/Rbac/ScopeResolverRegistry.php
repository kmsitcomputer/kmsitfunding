<?php

namespace App\Services\Rbac;

use App\Enums\ScopeType;

/**
 * Registry of every currently-available `ScopeResolver`, keyed by
 * `ScopeType`. A concrete scope type (one whose `requiresNullScopeId()` is
 * false — PARTNER, CAMPAIGN, PROGRAM, FUND, FUNDRAISER, BENEFICIARY_CASE,
 * ASSIGNED_WORK) with NO registered resolver here means that domain does not
 * exist yet — assignment against that scope type is REJECTED (fail-closed),
 * never silently allowed (see `IMP003-IMPL-M02`). A future domain stage
 * registers its own resolver here (or via its own service provider binding
 * into this same registry) once it exists; IMP-003 does not implement that
 * resolver itself.
 */
class ScopeResolverRegistry
{
    /** @var array<string, ScopeResolver> */
    private array $resolvers = [];

    /**
     * @param  iterable<ScopeResolver>  $resolvers
     */
    public function __construct(iterable $resolvers = [])
    {
        foreach ($resolvers as $resolver) {
            $this->register($resolver);
        }
    }

    public function register(ScopeResolver $resolver): void
    {
        $this->resolvers[$resolver->scopeType()->value] = $resolver;
    }

    public function has(ScopeType $scopeType): bool
    {
        return isset($this->resolvers[$scopeType->value]);
    }

    public function get(ScopeType $scopeType): ?ScopeResolver
    {
        return $this->resolvers[$scopeType->value] ?? null;
    }
}
