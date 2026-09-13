<?php

namespace Tests\Unit\Rbac;

use App\Services\Rbac\ApiAuthority;
use Tests\TestCase;

class ApiAuthorityTest extends TestCase
{
    public function test_effective_authority_is_the_intersection_not_the_union(): void
    {
        $principalPermissions = ['rbac.role.assign', 'rbac.authority.assign'];
        $tokenCapabilities = ['rbac.role.assign'];

        $effective = ApiAuthority::effective($principalPermissions, $tokenCapabilities);

        $this->assertSame(['rbac.role.assign'], $effective);
        $this->assertNotContains('rbac.authority.assign', $effective, 'A token must never expand Principal authority.');
    }

    public function test_a_broader_token_cannot_grant_a_permission_the_principal_lacks(): void
    {
        $principalPermissions = ['rbac.role.assign'];
        $tokenCapabilities = ['rbac.role.assign', 'rbac.authority.assign'];

        $effective = ApiAuthority::effective($principalPermissions, $tokenCapabilities);

        $this->assertSame(['rbac.role.assign'], $effective);
        $this->assertNotContains('rbac.authority.assign', $effective, 'The Principal cannot bypass its own authority via a broader token.');
    }

    public function test_no_overlap_yields_no_effective_authority(): void
    {
        $this->assertSame([], ApiAuthority::effective(['rbac.role.assign'], ['rbac.authority.assign']));
    }
}
