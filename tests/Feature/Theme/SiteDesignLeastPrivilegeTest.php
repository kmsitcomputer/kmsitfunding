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
use App\Services\Theme\ThemeNavigationService;
use App\Services\Theme\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * CR-001-C remediation N-07 — least-privilege authorization for Site Design.
 * Proves: theme.preview grants preview only; theme.view alone grants no
 * mutation; theme.update grants editing but not publish/preview; publish
 * remains separately authorized under theme.publish.
 */
class SiteDesignLeastPrivilegeTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private function makePrincipal(array $permissionCodes): Principal
    {
        $this->sequence++;
        $user = User::create([
            'email' => "leastpriv-test-{$this->sequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
        $principal = app(PrincipalService::class)->forUser($user);

        $role = Role::create([
            'code' => 'leastpriv-test-role-'.$this->sequence,
            'name' => 'LeastPriv Test Role '.$this->sequence,
        ]);

        foreach ($permissionCodes as $code) {
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

    private function makeTheme(string $status = 'DRAFT'): Theme
    {
        $owner = $this->makePrincipal([]);

        $theme = app(ThemeService::class)->create(['name' => 'LeastPriv '.uniqid()], $owner);
        $theme->forceFill(['status' => $status])->save();

        return $theme->fresh();
    }

    public function test_preview_only_actor_can_preview_but_cannot_mutate(): void
    {
        $theme = $this->makeTheme();
        $actor = $this->makePrincipal([PermissionRegistry::THEME_PREVIEW]);

        $this->actingAs($actor->humanUser)
            ->get("/admin/site-design/{$theme->ulid}/preview")
            ->assertOk();

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", [
                'color_tokens' => [
                    'primary' => '#111111',
                    'secondary' => '#222222',
                    'accent' => '#333333',
                    'neutral_bg' => '#ffffff',
                    'neutral_text' => '#000000',
                ],
            ])
            ->assertForbidden();

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/publish")
            ->assertForbidden();
        $this->assertSame('DRAFT', $theme->fresh()->status);
    }

    public function test_view_only_actor_cannot_preview(): void
    {
        $theme = $this->makeTheme();
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW]);

        $this->actingAs($actor->humanUser)
            ->get('/admin/site-design')
            ->assertOk();

        $this->actingAs($actor->humanUser)
            ->get("/admin/site-design/{$theme->ulid}/preview")
            ->assertForbidden();
    }

    public function test_update_actor_can_edit_but_cannot_publish_or_preview(): void
    {
        $theme = $this->makeTheme();
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", [
                'color_tokens' => [
                    'primary' => '#111111',
                    'secondary' => '#222222',
                    'accent' => '#333333',
                    'neutral_bg' => '#ffffff',
                    'neutral_text' => '#000000',
                ],
                'font_family' => 'system',
            ])
            ->assertRedirect();
        $this->assertNotNull($theme->fresh()->branding);

        $this->actingAs($actor->humanUser)
            ->get("/admin/site-design/{$theme->ulid}/preview")
            ->assertForbidden();

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/publish")
            ->assertForbidden();
        $this->assertSame('DRAFT', $theme->fresh()->status);
    }

    public function test_publish_actor_without_update_can_publish(): void
    {
        $theme = $this->makeTheme();
        $actor = $this->makePrincipal([PermissionRegistry::THEME_PUBLISH]);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/publish")
            ->assertRedirect();
        $this->assertSame('ACTIVE', $theme->fresh()->status);
    }

    public function test_update_actor_can_mutate_navigation_but_not_clone_without_create(): void
    {
        $theme = $this->makeTheme();
        $seeder = $this->makePrincipal([]);
        $menu = app(ThemeNavigationService::class)->createMenu($theme, [
            'code' => 'primary-'.uniqid(),
            'name' => 'Primary',
        ], $seeder);
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/menus/{$menu->ulid}/items", [
                'label' => 'Docs',
                'destination_type' => 'EXTERNAL_URL',
                'destination_external_url' => 'https://example.com/docs',
            ])
            ->assertRedirect();
        $this->assertSame(1, $menu->items()->count());

        $activeTheme = $this->makeTheme('ACTIVE');

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$activeTheme->ulid}/clone")
            ->assertForbidden();
    }

    public function test_view_update_preview_without_publish_cannot_publish(): void
    {
        $theme = $this->makeTheme();
        $actor = $this->makePrincipal([
            PermissionRegistry::THEME_VIEW,
            PermissionRegistry::THEME_UPDATE,
            PermissionRegistry::THEME_PREVIEW,
        ]);

        $this->actingAs($actor->humanUser)
            ->get("/admin/site-design/{$theme->ulid}/preview")
            ->assertOk();

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/publish")
            ->assertForbidden();
        $this->assertSame('DRAFT', $theme->fresh()->status);
    }
}
