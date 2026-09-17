<?php

namespace Tests\Feature\Campaign;

use App\Enums\ScopeType;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\Fund;
use App\Models\Campaign\Program;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Policies\CampaignPolicy;
use App\Policies\FundPolicy;
use App\Policies\ProgramPolicy;
use App\Services\Rbac\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-007 — mirrors ThemeAuthorizationTest/ContentAuthorizationTest exactly.
 * AC-007-013 (AUTHORIZED/UNAUTHORIZED-ROLE/OUT-OF-SCOPE/UNAUTHENTICATED
 * matrix) and AC-007-020 (CAMPAIGN_APPROVE and CAMPAIGN_PUBLISH never
 * substitute for each other, per HD-IMP007-01).
 */
class CampaignAuthorizationTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeProgram(): Program
    {
        $actor = $this->makeUnauthorizedActor();
        $program = new Program;
        $program->forceFill([
            'ulid' => (string) Str::ulid(),
            'name' => 'P', 'slug' => 'p-'.uniqid(), 'status' => 'DRAFT', 'edit_version' => 0,
            'created_by_principal_id' => $actor->id, 'updated_by_principal_id' => $actor->id,
        ])->save();

        return $program;
    }

    private function makeFund(): Fund
    {
        $actor = $this->makeUnauthorizedActor();
        $fund = new Fund;
        $fund->forceFill([
            'ulid' => (string) Str::ulid(),
            'name' => 'F', 'code' => 'f-'.uniqid(), 'status' => 'ACTIVE',
            'created_by_principal_id' => $actor->id, 'updated_by_principal_id' => $actor->id,
        ])->save();

        return $fund;
    }

    private function makeCampaign(string $status = 'DRAFT'): Campaign
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = new Campaign;
        $campaign->forceFill([
            'ulid' => (string) Str::ulid(),
            'name' => 'C', 'slug' => 'c-'.uniqid(), 'status' => $status, 'edit_version' => 0,
            'created_by_principal_id' => $actor->id, 'updated_by_principal_id' => $actor->id,
        ])->save();

        return $campaign;
    }

    public function test_program_policy_allows_authorized_and_denies_unauthorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $unauthorized = $this->makeUnauthorizedActor();
        $policy = new ProgramPolicy;
        $program = $this->makeProgram();

        $this->assertTrue($policy->view($authorized));
        $this->assertFalse($policy->view($unauthorized));
        $this->assertTrue($policy->create($authorized));
        $this->assertFalse($policy->create($unauthorized));
        $this->assertTrue($policy->update($authorized, $program));
        $this->assertFalse($policy->update($unauthorized, $program));
        $this->assertTrue($policy->publish($authorized, $program));
        $this->assertFalse($policy->publish($unauthorized, $program));
        $this->assertTrue($policy->archive($authorized, $program));
        $this->assertFalse($policy->archive($unauthorized, $program));
    }

    public function test_fund_policy_allows_authorized_and_denies_unauthorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $unauthorized = $this->makeUnauthorizedActor();
        $policy = new FundPolicy;
        $fund = $this->makeFund();

        $this->assertTrue($policy->view($authorized));
        $this->assertFalse($policy->view($unauthorized));
        $this->assertTrue($policy->create($authorized));
        $this->assertFalse($policy->create($unauthorized));
        $this->assertTrue($policy->update($authorized, $fund));
        $this->assertFalse($policy->update($unauthorized, $fund));
        $this->assertTrue($policy->archive($authorized, $fund));
        $this->assertFalse($policy->archive($unauthorized, $fund));
    }

    public function test_campaign_policy_allows_authorized_and_denies_unauthorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $unauthorized = $this->makeUnauthorizedActor();
        $policy = new CampaignPolicy;
        $draft = $this->makeCampaign('DRAFT');
        $review = $this->makeCampaign('REVIEW');
        $approved = $this->makeCampaign('APPROVED');
        $published = $this->makeCampaign('PUBLISHED');

        $this->assertTrue($policy->view($authorized));
        $this->assertFalse($policy->view($unauthorized));
        $this->assertTrue($policy->create($authorized));
        $this->assertFalse($policy->create($unauthorized));
        $this->assertTrue($policy->update($authorized, $draft));
        $this->assertFalse($policy->update($unauthorized, $draft));
        $this->assertTrue($policy->submit($authorized, $draft));
        $this->assertFalse($policy->submit($unauthorized, $draft));
        $this->assertTrue($policy->approve($authorized, $review));
        $this->assertFalse($policy->approve($unauthorized, $review));
        $this->assertTrue($policy->publish($authorized, $approved));
        $this->assertFalse($policy->publish($unauthorized, $approved));
        $this->assertTrue($policy->close($authorized, $published));
        $this->assertFalse($policy->close($unauthorized, $published));
    }

    public function test_campaign_policy_rejects_update_of_a_closed_campaign_even_for_authorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $closed = $this->makeCampaign('CLOSED');

        $this->assertFalse((new CampaignPolicy)->update($authorized, $closed));
    }

    public function test_campaign_policy_rejects_transitions_at_the_wrong_status_even_for_authorized_actor(): void
    {
        $authorized = $this->makeAuthorizedActor();
        $policy = new CampaignPolicy;
        $draft = $this->makeCampaign('DRAFT');

        $this->assertFalse($policy->approve($authorized, $draft));
        $this->assertFalse($policy->publish($authorized, $draft));
        $this->assertFalse($policy->close($authorized, $draft));
    }

    /**
     * AC-007-020 (HD-IMP007-01): CAMPAIGN_APPROVE and CAMPAIGN_PUBLISH are
     * independently enforceable — neither implies the other.
     */
    public function test_campaign_approve_and_publish_permissions_do_not_substitute_for_each_other(): void
    {
        $approveOnly = $this->makeGrantedActor([PermissionRegistry::CAMPAIGN_APPROVE]);
        $publishOnly = $this->makeGrantedActor([PermissionRegistry::CAMPAIGN_PUBLISH]);
        $policy = new CampaignPolicy;
        $review = $this->makeCampaign('REVIEW');
        $approved = $this->makeCampaign('APPROVED');

        $this->assertTrue($policy->approve($approveOnly, $review));
        $this->assertFalse($policy->publish($approveOnly, $approved), 'CAMPAIGN_APPROVE must never grant publish authority.');

        $this->assertTrue($policy->publish($publishOnly, $approved));
        $this->assertFalse($policy->approve($publishOnly, $review), 'CAMPAIGN_PUBLISH must never grant approve authority.');
    }

    private function makeGrantedActor(array $permissionCodes): Principal
    {
        $actor = $this->makeUnauthorizedActor();
        $role = Role::create(['code' => 'campaign_test_role_'.uniqid(), 'name' => 'Campaign Test Role']);

        foreach ($permissionCodes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['description' => 'test']);
            $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        }

        PrincipalRoleAssignment::create([
            'principal_id' => $actor->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);

        return $actor;
    }

    public function test_campaign_policy_denies_a_grant_at_a_scope_type_campaign_never_requests(): void
    {
        // The single-organization-baseline analog of "cross-scope attempt"
        // (CampaignScopeResolver: identical single-scope-dimension shape to
        // ContentScopeResolver/ThemeScopeResolver).
        $actor = $this->makeGrantedActorAtScope([PermissionRegistry::CAMPAIGN_UPDATE], ScopeType::Own);
        $campaign = $this->makeCampaign('DRAFT');

        $this->assertFalse((new CampaignPolicy)->update($actor, $campaign));
    }

    private function makeGrantedActorAtScope(array $permissionCodes, ScopeType $scopeType): Principal
    {
        $actor = $this->makeUnauthorizedActor();
        $role = Role::create(['code' => 'campaign_wrong_scope_'.uniqid(), 'name' => 'Campaign Wrong Scope']);

        foreach ($permissionCodes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['description' => 'test']);
            $role->permissions()->attach($permission->id, ['granted_at' => now()]);
        }

        PrincipalRoleAssignment::create([
            'principal_id' => $actor->id,
            'role_id' => $role->id,
            'scope_type' => $scopeType->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);

        return $actor;
    }
}
