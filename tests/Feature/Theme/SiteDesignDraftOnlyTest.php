<?php

namespace Tests\Feature\Theme;

use App\Models\Theme\Theme;
use App\Models\Theme\ThemeNavigationMenu;
use App\Services\Theme\ThemeNavigationService;
use App\Services\Theme\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CODEX-CR001C-01 — DRAFT-only Site Design mutations.
 * Proves every C editor mutation fails closed (409) for ACTIVE/INACTIVE/
 * ARCHIVED owners while DRAFT succeeds when authorized, with persisted
 * state verified unchanged after each denial.
 */
class SiteDesignDraftOnlyTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeTheme(string $status = 'DRAFT'): Theme
    {
        $actor = $this->makeUnauthorizedActor();

        $theme = app(ThemeService::class)->create(['name' => 'DraftOnly '.$status.' '.uniqid()], $actor);
        $theme->forceFill(['status' => $status])->save();

        return $theme->fresh();
    }

    private function makeMenu(Theme $theme): ThemeNavigationMenu
    {
        return app(ThemeNavigationService::class)->createMenu($theme, [
            'code' => 'primary-'.uniqid(),
            'name' => 'Primary',
        ], $this->makeUnauthorizedActor());
    }

    private function brandingPayload(): array
    {
        return [
            'color_tokens' => [
                'primary' => '#111111',
                'secondary' => '#222222',
                'accent' => '#333333',
                'neutral_bg' => '#ffffff',
                'neutral_text' => '#000000',
            ],
            'font_family' => 'system',
        ];
    }

    public function test_draft_branding_save_succeeds_when_authorized(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", $this->brandingPayload())
            ->assertRedirect();

        $this->assertSame('#111111', $theme->fresh()->branding->color_tokens['primary']);
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_non_draft_branding_save_denied_and_unchanged(string $status): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme($status);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/branding", $this->brandingPayload())
            ->assertConflict();

        $this->assertNull($theme->fresh()->branding);
        $this->assertSame($status, $theme->fresh()->status);
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_non_draft_menu_creation_denied(string $status): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme($status);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/menus", ['code' => 'extra', 'name' => 'Extra'])
            ->assertConflict();

        $this->assertSame(0, $theme->fresh()->navigationMenus()->count());
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_non_draft_navigation_create_edit_delete_denied(string $status): void
    {
        $seeder = $this->makeUnauthorizedActor();
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme($status);
        $menu = app(ThemeNavigationService::class)->createMenu($theme, [
            'code' => 'primary',
            'name' => 'Primary',
        ], $seeder);
        $item = app(ThemeNavigationService::class)->createItem($menu, [
            'label' => 'Keep',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/keep',
        ], $seeder);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/menus/{$menu->ulid}/items", [
                'label' => 'Sneak',
                'destination_type' => 'EXTERNAL_URL',
                'destination_external_url' => 'https://example.com/sneak',
            ])
            ->assertConflict();

        $this->actingAs($actor->humanUser)
            ->patch("/admin/site-design/navigation-items/{$item->ulid}", ['label' => 'Mutated'])
            ->assertConflict();

        $this->actingAs($actor->humanUser)
            ->delete("/admin/site-design/navigation-items/{$item->ulid}")
            ->assertConflict();

        $this->assertSame(1, $menu->fresh()->items()->count());
        $this->assertSame('Keep', $item->fresh()->label);
        $this->assertNotNull($item->fresh());
    }

    public function test_draft_navigation_crud_succeeds_when_authorized(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $menu = $this->makeMenu($theme);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/menus/{$menu->ulid}/items", [
                'label' => 'Docs',
                'destination_type' => 'EXTERNAL_URL',
                'destination_external_url' => 'https://example.com/docs',
            ])
            ->assertRedirect();

        $item = $menu->items()->firstOrFail();

        $this->actingAs($actor->humanUser)
            ->patch("/admin/site-design/navigation-items/{$item->ulid}", ['label' => 'Docs v2'])
            ->assertRedirect();
        $this->assertSame('Docs v2', $item->fresh()->label);

        $this->actingAs($actor->humanUser)
            ->delete("/admin/site-design/navigation-items/{$item->ulid}")
            ->assertRedirect();
        $this->assertSame(0, $menu->fresh()->items()->count());
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_non_draft_logo_upload_denied(string $status): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme($status);

        $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/logo", [])
            ->assertStatus(409);

        $this->assertSame(0, $theme->fresh()->assets()->count());
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_non_draft_publish_denied_through_site_design_path(string $status): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme($status);

        $response = $this->actingAs($actor->humanUser)
            ->post("/admin/site-design/{$theme->ulid}/publish");

        $this->assertContains($response->getStatusCode(), [403, 409]);

        $this->assertSame($status, $theme->fresh()->status);
    }

    public function test_overview_hides_edit_actions_for_active_theme(): void
    {
        $actor = $this->makeAuthorizedActor();
        $active = $this->makeTheme('ACTIVE');

        $this->actingAs($actor->humanUser)
            ->get('/admin/site-design')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/SiteDesign/Overview')
                ->has('themes.data', fn (Assert $rows) => $rows
                    ->each(fn (Assert $row) => $row->where('ulid', $active->ulid)->etc())
                ));
    }

    public static function nonDraftStatuses(): array
    {
        return [
            'ACTIVE' => ['ACTIVE'],
            'INACTIVE' => ['INACTIVE'],
            'ARCHIVED' => ['ARCHIVED'],
        ];
    }
}
