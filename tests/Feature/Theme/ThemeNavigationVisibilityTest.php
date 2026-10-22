<?php

namespace Tests\Feature\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeNavigationMenu;
use App\Services\Theme\ThemeNavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CR-001-C C-11 — ThemeNavigationService visibility persistence coverage.
 * Proves createItem() persists visible_desktop/visible_mobile when provided
 * and preserves backward-compatible TRUE defaults when omitted.
 */
class ThemeNavigationVisibilityTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function seedMenu(Principal $actor): ThemeNavigationMenu
    {
        $theme = Theme::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Nav Theme',
            'slug' => 'nav-theme-'.uniqid(),
            'status' => 'DRAFT',
            'is_system_default' => false,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);

        return app(ThemeNavigationService::class)->createMenu($theme, [
            'code' => 'primary',
            'name' => 'Primary',
        ], $actor);
    }

    public function test_create_item_persists_explicit_visibility_flags(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $menu = $this->seedMenu($actor);

        $item = app(ThemeNavigationService::class)->createItem($menu, [
            'label' => 'Desktop only',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/a',
            'visible_desktop' => false,
            'visible_mobile' => true,
        ], $actor);

        $this->assertFalse((bool) $item->fresh()->visible_desktop);
        $this->assertTrue((bool) $item->fresh()->visible_mobile);
        $this->assertTrue((bool) $item->fresh()->visible);
    }

    public function test_create_item_defaults_visibility_to_true_when_omitted(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $menu = $this->seedMenu($actor);

        $item = app(ThemeNavigationService::class)->createItem($menu, [
            'label' => 'Everywhere',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/b',
        ], $actor);

        $this->assertTrue((bool) $item->fresh()->visible_desktop);
        $this->assertTrue((bool) $item->fresh()->visible_mobile);
    }

    public function test_create_item_persists_both_false(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $menu = $this->seedMenu($actor);

        $item = app(ThemeNavigationService::class)->createItem($menu, [
            'label' => 'Hidden',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/c',
            'visible_desktop' => false,
            'visible_mobile' => false,
        ], $actor);

        $this->assertFalse((bool) $item->fresh()->visible_desktop);
        $this->assertFalse((bool) $item->fresh()->visible_mobile);
    }
}
