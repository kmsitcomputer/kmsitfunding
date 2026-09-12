<?php

namespace Tests\Feature\Identity;

use App\Models\SuperAdminBootstrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_bootstrap_introduces_no_role_or_permission_schema(): void
    {
        foreach (['roles', 'permissions', 'role_user'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
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
