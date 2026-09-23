<?php

namespace Tests\Feature\Theme;

use App\Enums\ScopeType;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\Theme\Theme;
use App\Models\User;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use App\Services\Theme\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CODEX-CR001C-03 — operator publish action.
 * Proves: authorized publish via Site Design route delegates to canonical
 * activation; unauthorized denied; preview exposes canPublish only to
 * publish-capable actors; Overview exposes canPublish capability flag.
 */
class SiteDesignPublishActionTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeTheme(string $status = 'DRAFT'): Theme
    {
        $actor = $this->makeUnauthorizedActor();

        $theme = app(ThemeService::class)->create(['name' => 'Publish Action '.$status.' '.uniqid()], $actor);
        $theme->forceFill(['status' => $status])->save();

        return $theme->fresh();
    }

    public function test_publish_authorized_actor_activates_draft(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/publish")
            ->assertRedirect('/admin/site-design');

        $this->assertSame('ACTIVE', $theme->fresh()->status);
    }

    public function test_publish_unauthorized_actor_denied_and_unchanged(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->makeTheme();

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/publish")
            ->assertForbidden();

        $this->assertSame('DRAFT', $theme->fresh()->status);
    }

    public function test_preview_exposes_publish_capability_to_authorized_actor(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();

        $this->actingAs($actor->humanUser)
            ->get("/admin/site-design/{$theme->ulid}/preview")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/SiteDesign/Preview')
                ->where('canPublish', true));
    }

    public function test_preview_hides_publish_capability_from_update_only_actor(): void
    {
        $seeder = $this->makeUnauthorizedActor();
        $theme = app(ThemeService::class)->create(['name' => 'No Publish '.uniqid()], $seeder)->fresh();

        $viewer = $this->makeAuthorizedActor();
        PrincipalRoleAssignment::where('principal_id', $viewer->id)->delete();
        $limited = $this->makeLimitedActor([
            PermissionRegistry::THEME_VIEW,
            PermissionRegistry::THEME_UPDATE,
            PermissionRegistry::THEME_PREVIEW,
        ]);

        $this->actingAs($limited->humanUser)
            ->get("/admin/site-design/{$theme->ulid}/preview")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/SiteDesign/Preview')
                ->where('canPublish', false));
    }

    public function test_overview_exposes_publish_capability_flag(): void
    {
        $actor = $this->makeAuthorizedActor();

        $this->actingAs($actor->humanUser)
            ->get('/admin/site-design')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/SiteDesign/Overview')
                ->where('canPublish', true));
    }

    public function test_overview_hides_publish_flag_from_view_only_actor(): void
    {
        $limited = $this->makeLimitedActor([PermissionRegistry::THEME_VIEW]);

        $this->actingAs($limited->humanUser)
            ->get('/admin/site-design')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/SiteDesign/Overview')
                ->where('canPublish', false));
    }

    private function makeLimitedActor(array $codes): Principal
    {
        $user = User::create([
            'email' => 'publish-limited-'.uniqid().'@example.com',
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
        $principal = app(PrincipalService::class)->forUser($user);

        $role = Role::create([
            'code' => 'publish-limited-'.uniqid(),
            'name' => 'Publish Limited',
        ]);

        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['description' => 'test']);
            $role->permissions()->attach($permission->id, ['granted_at' => now(), 'granted_by_principal_id' => null]);
        }

        PrincipalRoleAssignment::create([
            'principal_id' => $principal->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::GlobalPlatform->value,
            'scope_id' => null,
            'starts_at' => now(),
            'assigned_by_principal_id' => null,
        ]);

        return $principal;
    }
}
