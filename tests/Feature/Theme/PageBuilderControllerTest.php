<?php

namespace Tests\Feature\Theme;

use App\Models\Theme\Theme;
use App\Models\Theme\ThemeTemplate;
use App\Services\Theme\PageBuilderBlockService;
use App\Services\Theme\ThemeComponentService;
use App\Services\Theme\ThemeSectionService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CR-001-D — PageBuilderController HTTP coverage. Proves: authorized DRAFT
 * builder access, enable/disable, edit, canvas mapping (home/page/article),
 * preview contract reuse, guest/unauthorized denial.
 */
class PageBuilderControllerTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeTheme(string $status = 'DRAFT'): Theme
    {
        $actor = $this->makeUnauthorizedActor();

        $theme = app(ThemeService::class)->create(['name' => 'PB Controller '.uniqid()], $actor);
        $theme->forceFill(['status' => $status])->save();

        return $theme->fresh();
    }

    private function makeTemplate(Theme $theme, string $kind = 'home'): ThemeTemplate
    {
        return app(ThemeTemplateService::class)->create(
            $theme,
            ['name' => ucfirst($kind).' '.uniqid(), 'content_kind' => $kind],
            $this->makeUnauthorizedActor()
        );
    }

    private function ctaConfig(): array
    {
        return [
            'label' => 'Donate',
            'variant' => 'primary',
            // Carried forward on every edit — see PageBuilderBlockServiceTest.
            'intent' => 'donation',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/donate',
        ];
    }

    public function test_guest_cannot_access_builder_index(): void
    {
        $theme = $this->makeTheme();

        $this->get("/admin/page-builder/{$theme->ulid}")->assertRedirect('/login');
    }

    public function test_unauthorized_actor_is_forbidden_from_builder(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $this->actingAs($actor->humanUser)->get("/admin/page-builder/{$theme->ulid}")->assertForbidden();
        $this->actingAs($actor->humanUser)->get("/admin/page-builder/templates/{$template->ulid}")->assertForbidden();
    }

    public function test_authorized_actor_can_view_builder_index_and_canvas(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $this->makeTemplate($theme, 'home');

        $this->actingAs($actor->humanUser)
            ->get("/admin/page-builder/{$theme->ulid}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PageBuilder/Index')
                ->has('canvases'));
    }

    public function test_home_page_article_canvas_mapping(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();

        foreach (['home', 'page', 'article'] as $kind) {
            $template = $this->makeTemplate($theme, $kind);

            $this->actingAs($actor->humanUser)
                ->get("/admin/page-builder/templates/{$template->ulid}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Admin/PageBuilder/Show')
                    ->where('contentKind', $kind)
                    ->has('blocks'));
        }
    }

    public function test_authorized_draft_add_block_succeeds(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $this->actingAs($actor->humanUser)
            ->post("/admin/page-builder/templates/{$template->ulid}/blocks", [
                'block_key' => 'donation_cta',
                'config' => $this->ctaConfig(),
            ])
            ->assertRedirect();

        $this->assertSame(1, $template->fresh()->sections()->count());
    }

    public function test_authorized_draft_edit_block_succeeds(): void
    {
        $seeder = $this->makeAuthorizedActor();
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $section = app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', $this->ctaConfig(), $seeder);
        $expected = $section->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');

        $this->actingAs($actor->humanUser)
            ->patch("/admin/page-builder/blocks/{$section->ulid}", [
                'config' => array_merge($this->ctaConfig(), ['label' => 'Give']),
                'expected_updated_at' => $expected,
            ])
            ->assertRedirect();

        $this->assertSame('Give', $section->fresh()->components()->first()->config['label']);
    }

    public function test_enable_disable_block(): void
    {
        $seeder = $this->makeAuthorizedActor();
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $section = app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', $this->ctaConfig(), $seeder);

        $expected = $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP');
        $this->actingAs($actor->humanUser)
            ->patch("/admin/page-builder/blocks/{$section->ulid}/visibility", ['visible' => false, 'expected_updated_at' => $expected])
            ->assertRedirect();
        $this->assertFalse($section->fresh()->visible);

        $expected = $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP');
        $this->actingAs($actor->humanUser)
            ->patch("/admin/page-builder/blocks/{$section->ulid}/visibility", ['visible' => true, 'expected_updated_at' => $expected])
            ->assertRedirect();
        $this->assertTrue($section->fresh()->visible);
    }

    public function test_mutation_redirects_to_explicit_template_context(): void
    {
        $seeder = $this->makeAuthorizedActor();
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $home = $this->makeTemplate($theme, 'home');
        $page = $this->makeTemplate($theme, 'page');

        $section = app(PageBuilderBlockService::class)->addBlock($home, 'donation_cta', $this->ctaConfig(), $seeder);
        $section->forceFill(['is_reusable' => true])->save();
        app(ThemeSectionService::class)->placeReusable($page, $section->fresh(), $seeder);

        $expected = $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP');
        $this->actingAs($actor->humanUser)
            ->patch("/admin/page-builder/blocks/{$section->ulid}/visibility", [
                'visible' => false,
                'expected_updated_at' => $expected,
                'template_ulid' => $page->ulid,
            ])
            ->assertRedirect("/admin/page-builder/templates/{$page->ulid}");
    }

    public function test_visibility_requires_expected_updated_at_token(): void
    {
        $seeder = $this->makeAuthorizedActor();
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $section = app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', $this->ctaConfig(), $seeder);

        $this->actingAs($actor->humanUser)
            ->patch("/admin/page-builder/blocks/{$section->ulid}/visibility", ['visible' => false])
            ->assertSessionHasErrors('expected_updated_at');

        $this->actingAs($actor->humanUser)
            ->patch("/admin/page-builder/blocks/{$section->ulid}/visibility", ['visible' => false, 'expected_updated_at' => null])
            ->assertSessionHasErrors('expected_updated_at');

        $this->assertTrue($section->fresh()->visible);
    }

    public function test_update_requires_expected_updated_at_token(): void
    {
        $seeder = $this->makeAuthorizedActor();
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $section = app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', $this->ctaConfig(), $seeder);

        $this->actingAs($actor->humanUser)
            ->patch("/admin/page-builder/blocks/{$section->ulid}", ['config' => array_merge($this->ctaConfig(), ['label' => 'Sneak'])])
            ->assertSessionHasErrors('expected_updated_at');

        $this->assertSame('Donate', $section->fresh()->components()->first()->config['label']);
    }

    public function test_remove_requires_expected_section_updated_at_token(): void
    {
        $seeder = $this->makeAuthorizedActor();
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $section = app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', $this->ctaConfig(), $seeder);

        $this->actingAs($actor->humanUser)
            ->delete("/admin/page-builder/templates/{$template->ulid}/blocks/{$section->ulid}")
            ->assertSessionHasErrors('expected_section_updated_at');

        $this->assertTrue($template->fresh()->sections()->whereKey($section->id)->exists());
    }

    public function test_preview_contract_links_to_existing_site_design_preview(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();

        $this->actingAs($actor->humanUser)
            ->get("/admin/site-design/{$theme->ulid}/preview")
            ->assertOk();

        $active = $this->makeTheme('ACTIVE');

        $this->actingAs($actor->humanUser)
            ->get("/admin/site-design/{$active->ulid}/preview")
            ->assertNotFound();
    }

    public function test_stale_remove_and_visibility_conflict_over_http(): void
    {
        $seeder = $this->makeAuthorizedActor();
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $section = app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', $this->ctaConfig(), $seeder);
        $stale = $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP');

        app(PageBuilderBlockService::class)->setVisibility($section->fresh(), false, $seeder, $stale);

        // RA-04/N-04: delivered as a flashed validation error (not a raw
        // 409 abort) so the message is guaranteed to reach the operator
        // regardless of APP_DEBUG — see PageBuilderController::update().
        $this->actingAs($actor->humanUser)
            ->patch("/admin/page-builder/blocks/{$section->ulid}/visibility", [
                'visible' => true,
                'expected_updated_at' => $stale,
            ])
            ->assertSessionHasErrors('section');

        $this->actingAs($actor->humanUser)
            ->delete("/admin/page-builder/templates/{$template->ulid}/blocks/{$section->ulid}", [
                'expected_section_updated_at' => $stale,
            ])
            ->assertSessionHasErrors('section');

        $this->assertTrue($template->fresh()->sections()->whereKey($section->id)->exists());
        $this->assertFalse($section->fresh()->visible);
    }

    public function test_unauthorized_actor_cannot_mutate_blocks(): void
    {
        $seeder = $this->makeAuthorizedActor();
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $this->actingAs($actor->humanUser)
            ->post("/admin/page-builder/templates/{$template->ulid}/blocks", [
                'block_key' => 'donation_cta',
                'config' => $this->ctaConfig(),
            ])
            ->assertForbidden();

        $section = app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', $this->ctaConfig(), $seeder);

        $this->actingAs($actor->humanUser)
            ->patch("/admin/page-builder/blocks/{$section->ulid}", ['config' => $this->ctaConfig()])
            ->assertForbidden();

        $this->actingAs($actor->humanUser)
            ->delete("/admin/page-builder/templates/{$template->ulid}/blocks/{$section->ulid}")
            ->assertForbidden();

        $this->assertSame(1, $template->fresh()->sections()->count());
    }

    /**
     * RA-03/N-03 — the Configure/Duplicate/Remove controls Show.vue offers
     * are driven by the SAME canonical `isSectionBuilderManaged()` check the
     * service enforces before persistence, so a technical Section (here: a
     * single-component `navigation_menu_slot`, which a naive
     * `component-count > 1` heuristic would miss entirely) never shows an
     * action the server would reject anyway.
     */
    public function test_technical_section_ui_flags_hide_unsupported_actions(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $normal = app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', $this->ctaConfig(), $actor);

        $technicalSection = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        app(ThemeComponentService::class)->create($technicalSection, 'navigation_menu_slot', ['menu_code' => 'primary'], $actor);

        $this->actingAs($actor->humanUser)
            ->get("/admin/page-builder/templates/{$template->ulid}")
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($normal, $technicalSection) {
                $page->component('Admin/PageBuilder/Show')->has('blocks', 2);
                $page->where('blocks', function ($blocks) use ($normal, $technicalSection) {
                    $byUlid = collect($blocks)->keyBy('ulid');
                    $normalRow = $byUlid[$normal->ulid];
                    $technicalRow = $byUlid[$technicalSection->ulid];

                    return $normalRow['canConfigure'] === true
                        && $normalRow['canDuplicate'] === true
                        && $normalRow['canRemove'] === true
                        && $normalRow['isTechnical'] === false
                        && $technicalRow['canConfigure'] === false
                        && $technicalRow['canDuplicate'] === false
                        && $technicalRow['canRemove'] === false
                        && $technicalRow['isTechnical'] === true;
                });
            });
    }
}
