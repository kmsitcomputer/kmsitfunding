<?php

namespace Tests\Support\Rbac;

/**
 * Test-only fixture standing in for a future Partner-domain resource — no
 * Partner domain exists yet (IMP-003 must not implement one prematurely).
 * Used only to exercise the PARTNER ScopeType branch of the AND-chain
 * against a concrete resolvable target, per the specification's own
 * allowance for "an approved test fixture/fake authorization resource
 * contract" where a real domain resource doesn't exist yet.
 */
final class FakePartnerResource
{
    public function __construct(public readonly int $partnerId) {}
}
