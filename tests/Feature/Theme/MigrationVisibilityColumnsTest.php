<?php

namespace Tests\Feature\Theme;

use App\Models\Theme\Theme;
use App\Models\Theme\ThemeNavigationItem;
use App\Models\Theme\ThemeNavigationMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CR-001-B (Schema #1/#2) — proves the additive visibility columns exist,
 * default TRUE, and that a pre-existing row (created without specifying
 * them) is unaffected — old rows behave exactly as before.
 */
class MigrationVisibilityColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_desktop_and_visible_mobile_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('theme_navigation_items', ['visible_desktop', 'visible_mobile']));
    }

    public function test_an_existing_row_defaults_both_columns_to_true(): void
    {
        $theme = Theme::create([
            'ulid' => (string) Str::ulid(), 'name' => 'T', 'slug' => 'mvc-'.uniqid(),
            'status' => 'DRAFT', 'is_system_default' => false,
        ]);
        $menu = ThemeNavigationMenu::create(['ulid' => (string) Str::ulid(), 'theme_id' => $theme->id, 'code' => 'main', 'name' => 'Main']);

        $item = ThemeNavigationItem::create([
            'ulid' => (string) Str::ulid(),
            'theme_navigation_menu_id' => $menu->id,
            'label' => 'Home',
            'destination_type' => 'SYSTEM_ROUTE',
            'destination_route' => 'home',
            'position' => 0,
        ])->refresh();

        $this->assertTrue($item->visible_desktop);
        $this->assertTrue($item->visible_mobile);
    }
}
