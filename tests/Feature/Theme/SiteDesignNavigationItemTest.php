<?php

namespace Tests\Feature\Theme;

use App\Enums\ScopeType;
use App\Models\Rbac\Permission;
use App\Models\Rbac\Principal;
use App\Models\Rbac\PrincipalRoleAssignment;
use App\Models\Rbac\Role;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeNavigationItem;
use App\Models\Theme\ThemeNavigationMenu;
use App\Models\User;
use App\Services\Rbac\PermissionRegistry;
use App\Services\Rbac\PrincipalService;
use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\ThemeNavigationService;
use App\Services\Theme\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CR-001-C remediation G-02 — Site Design navigation item management.
 * Proves: authorized create/edit/delete via canonical service, visibility
 * persistence, unauthorized denial, preview-only insufficiency, and
 * cross-menu parent rejection.
 */
class SiteDesignNavigationItemTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private function makePrincipal(array $permissionCodes): Principal
    {
        $this->sequence++;
        $user = User::create([
            'email' => "navitem-test-{$this->sequence}@example.com",
            'password' => Hash::make('correct-horse-battery-staple'),
        ]);
        $principal = app(PrincipalService::class)->forUser($user);

        $role = Role::create([
            'code' => 'navitem-test-role-'.$this->sequence,
            'name' => 'NavItem Test Role '.$this->sequence,
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

    private function makeTheme(): Theme
    {
        $owner = $this->makePrincipal([]);

        return app(ThemeService::class)->create(['name' => 'Nav Theme '.uniqid()], $owner)->fresh();
    }

    private function makeMenu(Theme $theme): ThemeNavigationMenu
    {
        $owner = $this->makePrincipal([]);

        return app(ThemeNavigationService::class)->createMenu($theme, [
            'code' => 'primary-'.uniqid(),
            'name' => 'Primary',
        ], $owner);
    }

    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Docs',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/docs',
            'visible_desktop' => true,
            'visible_mobile' => false,
        ], $overrides);
    }

    public function test_authorized_operator_can_create_navigation_item(): void
    {
        $theme = $this->makeTheme();
        $menu = $this->makeMenu($theme);
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/menus/{$menu->ulid}/items", $this->itemPayload())
            ->assertRedirect();

        $item = $menu->items()->firstOrFail();
        $this->assertSame('Docs', $item->label);
        $this->assertTrue((bool) $item->visible_desktop);
        $this->assertFalse((bool) $item->visible_mobile);
    }

    public function test_visible_desktop_and_mobile_persist_on_create(): void
    {
        $theme = $this->makeTheme();
        $menu = $this->makeMenu($theme);
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/menus/{$menu->ulid}/items", $this->itemPayload([
                'visible_desktop' => false,
                'visible_mobile' => true,
            ]))
            ->assertRedirect();

        $item = $menu->items()->firstOrFail();
        $this->assertFalse((bool) $item->fresh()->visible_desktop);
        $this->assertTrue((bool) $item->fresh()->visible_mobile);
    }

    public function test_item_can_be_edited_through_canonical_behavior(): void
    {
        $theme = $this->makeTheme();
        $menu = $this->makeMenu($theme);
        $editor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);
        $item = app(ThemeNavigationService::class)->createItem($menu, [
            'label' => 'Old',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/old',
        ], $this->makePrincipal([]));

        $this->actingAs($editor->humanUser)
            ->patch("/admin/site-design/navigation-items/{$item->ulid}", [
                'label' => 'New',
                'visible_desktop' => false,
                'visible_mobile' => false,
            ])
            ->assertRedirect();

        $fresh = $item->fresh();
        $this->assertSame('New', $fresh->label);
        $this->assertFalse((bool) $fresh->visible_desktop);
        $this->assertFalse((bool) $fresh->visible_mobile);
        $this->assertSame('https://example.com/old', $fresh->destination_external_url);
    }

    public function test_item_can_be_deleted_through_canonical_behavior(): void
    {
        $theme = $this->makeTheme();
        $menu = $this->makeMenu($theme);
        $editor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);
        $item = app(ThemeNavigationService::class)->createItem($menu, [
            'label' => 'Gone',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/gone',
        ], $this->makePrincipal([]));

        $this->actingAs($editor->humanUser)
            ->delete("/admin/site-design/navigation-items/{$item->ulid}")
            ->assertRedirect();

        $this->assertNull(ThemeNavigationItem::query()->whereKey($item->id)->first());
    }

    public function test_unauthorized_actor_cannot_create(): void
    {
        $theme = $this->makeTheme();
        $menu = $this->makeMenu($theme);
        $actor = $this->makePrincipal([]);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/menus/{$menu->ulid}/items", $this->itemPayload())
            ->assertForbidden();
        $this->assertSame(0, $menu->items()->count());
    }

    public function test_unauthorized_actor_cannot_edit(): void
    {
        $theme = $this->makeTheme();
        $menu = $this->makeMenu($theme);
        $item = app(ThemeNavigationService::class)->createItem($menu, [
            'label' => 'Stay',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/stay',
        ], $this->makePrincipal([]));
        $actor = $this->makePrincipal([]);

        $this->actingAs($actor->humanUser)
            ->patch("/admin/site-design/navigation-items/{$item->ulid}", ['label' => 'Hacked'])
            ->assertForbidden();
        $this->assertSame('Stay', $item->fresh()->label);
    }

    public function test_unauthorized_actor_cannot_delete(): void
    {
        $theme = $this->makeTheme();
        $menu = $this->makeMenu($theme);
        $item = app(ThemeNavigationService::class)->createItem($menu, [
            'label' => 'Keep',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/keep',
        ], $this->makePrincipal([]));
        $actor = $this->makePrincipal([]);

        $this->actingAs($actor->humanUser)
            ->delete("/admin/site-design/navigation-items/{$item->ulid}")
            ->assertForbidden();
        $this->assertNotNull($item->fresh());
    }

    public function test_preview_permission_alone_does_not_grant_navigation_mutation(): void
    {
        $theme = $this->makeTheme();
        $menu = $this->makeMenu($theme);
        $viewer = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_PREVIEW]);
        $item = app(ThemeNavigationService::class)->createItem($menu, [
            'label' => 'Listed',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/listed',
        ], $this->makePrincipal([]));

        $this->actingAs($viewer->humanUser)
            ->post("/admin/site-design/menus/{$menu->ulid}/items", $this->itemPayload())
            ->assertForbidden();

        $this->actingAs($viewer->humanUser)
            ->patch("/admin/site-design/navigation-items/{$item->ulid}", ['label' => 'Changed'])
            ->assertForbidden();

        $this->actingAs($viewer->humanUser)
            ->delete("/admin/site-design/navigation-items/{$item->ulid}")
            ->assertForbidden();

        $this->assertSame(1, $menu->items()->count());
        $this->assertSame('Listed', $item->fresh()->label);
    }

    public function test_cross_menu_parent_assignment_is_rejected(): void
    {
        $theme = $this->makeTheme();
        $menuA = $this->makeMenu($theme);
        $menuB = $this->makeMenu($theme);
        $seeder = $this->makePrincipal([]);
        $foreignParent = app(ThemeNavigationService::class)->createItem($menuB, [
            'label' => 'Foreign',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/foreign',
        ], $seeder);
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/menus/{$menuA->ulid}/items", $this->itemPayload([
                'parent_id' => $foreignParent->id,
            ]))
            ->assertSessionHasErrors();

        $this->assertSame(0, $menuA->items()->count());
    }

    public function test_child_of_child_parent_assignment_is_rejected(): void
    {
        $theme = $this->makeTheme();
        $menu = $this->makeMenu($theme);
        $seeder = $this->makePrincipal([]);
        $root = app(ThemeNavigationService::class)->createItem($menu, [
            'label' => 'Root',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/root',
        ], $seeder);
        $child = app(ThemeNavigationService::class)->createItem($menu, [
            'label' => 'Child',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/child',
            'parent_id' => $root->id,
        ], $seeder);
        $actor = $this->makePrincipal([PermissionRegistry::THEME_VIEW, PermissionRegistry::THEME_UPDATE]);

        $this->actingAs($actor->humanUser)
            ->patch("/admin/site-design/navigation-items/{$root->ulid}", ['parent_id' => $child->id])
            ->assertSessionHasErrors();

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_theme_view_alone_does_not_grant_navigation_mutation(): void
    {
        $theme = $this->makeTheme();
        $menu = $this->makeMenu($theme);
        $viewer = $this->makePrincipal([PermissionRegistry::THEME_VIEW]);

        $this->actingAs($viewer->humanUser)
            ->post("/admin/site-design/menus/{$menu->ulid}/items", $this->itemPayload())
            ->assertForbidden();
        $this->assertSame(0, $menu->items()->count());
    }

    public function test_update_item_service_rejects_cross_theme_parent(): void
    {
        $theme = $this->makeTheme();
        $menuA = $this->makeMenu($theme);
        $otherTheme = Theme::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Other',
            'slug' => 'other-'.uniqid(),
            'status' => 'DRAFT',
            'is_system_default' => false,
            'created_by_principal_id' => $this->makePrincipal([])->id,
            'updated_by_principal_id' => $this->makePrincipal([])->id,
        ]);
        $menuOther = $this->makeMenu($otherTheme);
        $seeder = $this->makePrincipal([]);
        $item = app(ThemeNavigationService::class)->createItem($menuA, [
            'label' => 'Mine',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/mine',
        ], $seeder);
        $foreignParent = app(ThemeNavigationService::class)->createItem($menuOther, [
            'label' => 'Foreign',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/foreign2',
        ], $seeder);

        $this->expectException(ThemeValidationException::class);

        app(ThemeNavigationService::class)->updateItem($item, ['parent_id' => $foreignParent->id], $seeder);
    }
}
