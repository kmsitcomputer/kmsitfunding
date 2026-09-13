<?php

namespace Tests\Feature\Identity;

use App\Models\SuperAdminBootstrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BootstrapSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_bootstrap_succeeds_with_no_default_credential(): void
    {
        $this->artisan('identity:bootstrap-super-admin')
            ->expectsQuestion('Super Admin email', 'root@example.com')
            ->expectsQuestion('Super Admin password', 'a-very-strong-password-123')
            ->expectsQuestion('Confirm password', 'a-very-strong-password-123')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'root@example.com']);
        $this->assertSame(1, SuperAdminBootstrap::count());

        $user = User::where('email', 'root@example.com')->first();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_repeat_bootstrap_is_refused(): void
    {
        $this->artisan('identity:bootstrap-super-admin')
            ->expectsQuestion('Super Admin email', 'root@example.com')
            ->expectsQuestion('Super Admin password', 'a-very-strong-password-123')
            ->expectsQuestion('Confirm password', 'a-very-strong-password-123')
            ->assertExitCode(0);

        // The lock is checked before any prompt is issued — a second operator
        // is refused immediately rather than asked for credentials that would
        // be moot the instant the guard is checked.
        $this->artisan('identity:bootstrap-super-admin')
            ->assertExitCode(1);

        $this->assertSame(1, SuperAdminBootstrap::count());
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    public function test_no_public_route_can_bootstrap_super_admin(): void
    {
        // The bootstrap command is the ONLY path — assert none of the public
        // registration/invitation endpoints reference it, and no such route
        // is registered.
        $this->assertFalse(Route::has('super-admin.bootstrap'));
    }

    /**
     * IMP-003 has since added `roles`/`permissions` (Principal-scoped, not
     * User-scoped) — this test's enduring intent is that the BOOTSTRAP
     * COMMAND ITSELF grants no role/permission as a side effect of identity
     * creation, not that no RBAC schema exists anywhere in the codebase.
     */
    public function test_bootstrap_introduces_no_role_or_permission_grant(): void
    {
        $this->assertFalse(Schema::hasTable('role_user'), 'Role assignment is Principal-scoped (IMP-003), never User-scoped.');

        $this->artisan('identity:bootstrap-super-admin')
            ->expectsQuestion('Super Admin email', 'root@example.com')
            ->expectsQuestion('Super Admin password', 'a-very-strong-password-123')
            ->expectsQuestion('Confirm password', 'a-very-strong-password-123')
            ->assertExitCode(0);

        if (Schema::hasTable('principal_role_assignments')) {
            $this->assertSame(0, DB::table('principal_role_assignments')->count(), 'Bootstrap must grant no Role — that is IMP-003\'s separate Bridge mechanism.');
        }
    }

    /**
     * IMP002-IMPL-M09 — the durable one-time guard must survive deletion of
     * the User it was created for. The permanent fact the guard preserves is
     * "an initial bootstrap has occurred," never "the original bootstrap User
     * still exists" — deleting that User must not resurrect the ability to
     * bootstrap a second Super Admin identity.
     */
    public function test_bootstrap_guard_survives_deletion_of_the_bootstrapped_user(): void
    {
        $this->artisan('identity:bootstrap-super-admin')
            ->expectsQuestion('Super Admin email', 'root@example.com')
            ->expectsQuestion('Super Admin password', 'a-very-strong-password-123')
            ->expectsQuestion('Confirm password', 'a-very-strong-password-123')
            ->assertExitCode(0);

        $this->assertSame(1, SuperAdminBootstrap::count());

        User::where('email', 'root@example.com')->first()->delete();

        $this->assertSame(1, SuperAdminBootstrap::count(), 'The guard row must survive User deletion.');
        $this->assertNull(SuperAdminBootstrap::first()->user_id);

        $this->artisan('identity:bootstrap-super-admin')
            ->assertExitCode(1);

        $this->assertSame(1, SuperAdminBootstrap::count());
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }
}
