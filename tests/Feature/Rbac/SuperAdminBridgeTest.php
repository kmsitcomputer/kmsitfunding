<?php

namespace Tests\Feature\Rbac;

use App\Enums\ScopeType;
use App\Models\Rbac\AuthorityAssignment;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\SuperAdminBootstrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminBridgeTest extends TestCase
{
    use RefreshDatabase;

    private function seedSuperAdminRole(): Role
    {
        return Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
    }

    private function bootstrapIdentity(): User
    {
        $user = User::create(['email' => 'bridge-root@example.com', 'password' => Hash::make('correct-horse-battery-staple')]);
        $user->forceFill(['email_verified_at' => now()])->save();
        SuperAdminBootstrap::create(['lock_key' => SuperAdminBootstrap::LOCK_KEY, 'user_id' => $user->id, 'bootstrapped_at' => now()]);

        return $user;
    }

    public function test_bridge_refuses_when_no_bootstrap_exists(): void
    {
        $this->seedSuperAdminRole();

        $this->artisan('rbac:bridge-first-super-admin')->assertExitCode(1);
        $this->assertSame(0, PrincipalRoleAssignment::count());
    }

    public function test_bridge_grants_only_super_admin_role_at_global_platform_scope(): void
    {
        $this->seedSuperAdminRole();
        $user = $this->bootstrapIdentity();

        $this->artisan('rbac:bridge-first-super-admin')->assertExitCode(0);

        $principal = Principal::where('human_user_id', $user->id)->firstOrFail();
        $role = Role::where('code', 'super_admin')->firstOrFail();

        $assignment = PrincipalRoleAssignment::where('principal_id', $principal->id)
            ->where('role_id', $role->id)
            ->whereNull('revoked_at')
            ->first();

        $this->assertNotNull($assignment);
        $this->assertSame(ScopeType::GlobalPlatform, $assignment->scope_type);
        $this->assertNull($assignment->scope_id);
        $this->assertNull($assignment->assigned_by_principal_id, 'Attributed to the bootstrap event itself, never a self-grant.');

        $this->assertSame(0, AuthorityAssignment::count(), 'The Bridge must grant no Business/Financial/Approval Authority.');
    }

    public function test_bridge_cannot_run_twice(): void
    {
        $this->seedSuperAdminRole();
        $this->bootstrapIdentity();

        $this->artisan('rbac:bridge-first-super-admin')->assertExitCode(0);
        $this->artisan('rbac:bridge-first-super-admin')->assertExitCode(1);

        $role = Role::where('code', 'super_admin')->firstOrFail();
        $this->assertSame(
            1,
            PrincipalRoleAssignment::where('role_id', $role->id)->count(),
            'A second bridge run must never produce a second assignment.'
        );
    }

    public function test_bridge_refuses_when_bootstrapped_identity_no_longer_exists(): void
    {
        $this->seedSuperAdminRole();
        $user = $this->bootstrapIdentity();
        $user->delete();

        $this->artisan('rbac:bridge-first-super-admin')->assertExitCode(1);
        $this->assertSame(0, PrincipalRoleAssignment::count());
    }
}
