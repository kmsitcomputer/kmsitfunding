<?php

namespace Tests\Feature\Theme;

use App\Models\Theme\Theme;
use App\Models\Theme\ThemeComponent;
use App\Models\Theme\ThemeSection;
use App\Models\Theme\ThemeTemplate;
use App\Policies\ContentArticlePolicy;
use App\Policies\ContentPagePolicy;
use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\PageBuilderBlockService;
use App\Services\Theme\ThemeAssetService;
use App\Services\Theme\ThemeAuditLogger;
use App\Services\Theme\ThemeComponentService;
use App\Services\Theme\ThemeSectionService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CR-001-D (+ bundled remediation) — PageBuilderBlockService orchestration
 * coverage. Proves: add / edit / remove / duplicate blocks, unknown-key
 * fail-closed, invalid-config rejection, cross-theme asset rejection with
 * state unchanged, ACTIVE source unchanged, reusable placement preserved,
 * transaction rollback, CTA destination validation, registry allowlist,
 * service-level DRAFT guard, stale remove/visibility.
 */
class PageBuilderBlockServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeTheme(string $status = 'DRAFT'): Theme
    {
        $actor = $this->makeUnauthorizedActor();

        $theme = app(ThemeService::class)->create(['name' => 'PB Service '.uniqid()], $actor);
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
            // Present so a config round-tripped through updateBlockConfig()
            // (which replaces the whole config, unlike addBlock()'s registry
            // merge) still matches the 'donation_cta' registry entry — the
            // real edit form always carries this field forward from the
            // persisted config it loaded.
            'intent' => 'donation',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/donate',
        ];
    }

    public function test_add_block_creates_section_and_component(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $section = app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', $this->ctaConfig(), $actor);

        $this->assertTrue($template->sections()->whereKey($section->id)->exists());
        $this->assertSame(1, $section->components()->count());
        $this->assertSame('cta_button', $section->components()->first()->type);
    }

    public function test_edit_block_updates_component_config(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $section = $service->addBlock($template, 'donation_cta', $this->ctaConfig(), $actor);
        $expected = $section->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');

        $updated = $service->updateBlockConfig($section, array_merge($this->ctaConfig(), ['label' => 'Give']), $actor, $expected);

        $this->assertSame('Give', $updated->fresh()->config['label']);
    }

    public function test_remove_block_detaches_and_deletes_non_reusable_section(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $section = $service->addBlock($template, 'donation_cta', $this->ctaConfig(), $actor);
        $sectionId = $section->id;
        $expectedSection = $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP');
        $expectedComponent = $section->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');

        $service->removeBlock($template, $section, $actor, $expectedSection, $expectedComponent);

        $this->assertSame(0, $template->sections()->count());
        $this->assertNull(ThemeSection::query()->whereKey($sectionId)->first());
    }

    public function test_duplicate_block_copies_type_and_config_after_source(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $source = $service->addBlock($template, 'donation_cta', $this->ctaConfig(), $actor);

        $duplicate = $service->duplicateBlock($template, $source, $actor);

        $this->assertNotSame($source->id, $duplicate->id);
        $this->assertSame('cta_button', $duplicate->components()->first()->type);
        $this->assertSame('Donate', $duplicate->components()->first()->config['label']);

        $ordered = $template->sections()->orderByPivot('position')->pluck('theme_sections.id')->all();
        $this->assertSame([$source->id, $duplicate->id], array_map('intval', $ordered));
    }

    public function test_duplicate_reorder_failure_rolls_back_creation(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $source = $service->addBlock($template, 'donation_cta', $this->ctaConfig(), $actor);
        $sectionsBefore = ThemeSection::query()->count();
        $componentsBefore = ThemeComponent::query()->count();

        $failingSections = \Mockery::mock(ThemeSectionService::class, [app(ThemeAuditLogger::class)])->makePartial();
        $failingSections->shouldReceive('reorder')->andThrow(
            new ThemeValidationException('reorder_set_mismatch', 'Injected reorder failure.')
        );
        $faulty = new PageBuilderBlockService(
            $failingSections,
            app(ThemeComponentService::class),
            app(ContentPagePolicy::class),
            app(ContentArticlePolicy::class),
        );

        try {
            $faulty->duplicateBlock($template, $source, $actor);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('reorder_set_mismatch', $e->reason);
        }

        $this->assertSame($sectionsBefore, ThemeSection::query()->count());
        $this->assertSame($componentsBefore, ThemeComponent::query()->count());
        $this->assertSame(1, $template->fresh()->sections()->count());
    }

    public function test_unknown_block_key_is_rejected_before_any_row_created(): void
    {
        $this->expectException(ThemeValidationException::class);

        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        try {
            app(PageBuilderBlockService::class)->addBlock($template, 'video_player', [], $actor);
        } finally {
            $this->assertSame(0, $template->sections()->count());
        }
    }

    public function test_raw_component_type_without_registry_key_is_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        try {
            app(PageBuilderBlockService::class)->addBlock($template, 'navigation_menu_slot', ['menu_code' => 'primary'], $actor);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('unknown_page_builder_block', $e->reason);
            $this->assertSame(0, $template->sections()->count());
        }
    }

    public function test_block_disallowed_on_canvas_is_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $article = $this->makeTemplate($theme, 'article');

        try {
            app(PageBuilderBlockService::class)->addBlock($article, 'campaign_grid', [
                'content_kind' => 'campaign', 'limit' => 6, 'order' => 'latest',
            ], $actor);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('block_not_allowed_on_canvas', $e->reason);
            $this->assertSame(0, $article->sections()->count());
        }
    }

    public function test_each_card_grid_operator_block_creates_with_registry_defaults(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        foreach (['ziswaf_services', 'gallery', 'partners'] as $blockKey) {
            $section = $service->addBlock($template, $blockKey, [], $actor);

            $this->assertSame('card_grid', $section->components()->first()->type);
            $this->assertSame('authored', $section->components()->first()->config['mode']);
            $this->assertNotEmpty($section->components()->first()->config['cards']);
        }

        $this->assertSame(3, $template->sections()->count());
    }

    public function test_edited_cards_validate_and_persist(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $section = $service->addBlock($template, 'partners', [], $actor);
        $expected = $section->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');

        $updated = $service->updateBlockConfig($section, [
            'mode' => 'authored',
            'cards' => [
                ['title' => 'Acme', 'text' => 'Gold partner', 'destination_type' => 'EXTERNAL_URL', 'destination_external_url' => 'https://acme.example'],
            ],
        ], $actor, $expected);

        $this->assertSame('Acme', $updated->fresh()->config['cards'][0]['title']);
    }

    public function test_invalid_config_is_rejected_and_section_rolled_back(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        try {
            app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', ['label' => 'x'], $actor);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException) {
            $this->assertSame(0, $template->sections()->count());
            $this->assertSame(0, ThemeComponent::query()->count());
        }
    }

    public function test_safe_cta_destination_accepted_and_unsafe_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $section = $service->addBlock($template, 'donation_cta', $this->ctaConfig(), $actor);
        $this->assertNotNull($section->id);

        $this->expectException(ThemeValidationException::class);
        $service->addBlock($template, 'donation_cta', [
            'label' => 'Evil',
            'variant' => 'primary',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'javascript:alert(1)',
        ], $actor);
    }

    public function test_cross_theme_asset_reference_rejected_and_state_unchanged(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $foreignUlid = str_repeat('A', 25).'1';
        $componentsBefore = ThemeComponent::query()->count();

        try {
            app(PageBuilderBlockService::class)->addBlock($template, 'hero', [
                'headline' => 'Hi',
                'background_theme_asset_ulid' => $foreignUlid,
            ], $actor);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('cross_theme_asset', $e->reason);
        }

        $this->assertSame(0, $template->fresh()->sections()->count());
        $this->assertSame($componentsBefore, ThemeComponent::query()->count());
    }

    public function test_source_active_theme_unchanged_after_draft_edits(): void
    {
        $actor = $this->makeAuthorizedActor();
        $active = $this->makeTheme('ACTIVE');
        $draft = $this->makeTheme();
        $template = $this->makeTemplate($draft);

        app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', $this->ctaConfig(), $actor);

        $this->assertSame('ACTIVE', $active->fresh()->status);
        $this->assertSame(0, $active->templates()->count());
    }

    public function test_service_rejects_non_draft_mutation_directly(): void
    {
        $this->expectException(ThemeValidationException::class);

        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme('ACTIVE');
        $template = $this->makeTemplate($theme);

        app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', $this->ctaConfig(), $actor);
    }

    public function test_reusable_section_placement_preserved_elsewhere_after_remove(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $home = $this->makeTemplate($theme, 'home');
        $page = $this->makeTemplate($theme, 'page');

        $section = app(ThemeSectionService::class)->createAndPlace($home, ['is_reusable' => true], $actor);
        app(ThemeComponentService::class)->create($section, 'cta_button', $this->ctaConfig(), $actor);
        app(ThemeSectionService::class)->placeReusable($page, $section->fresh(), $actor);

        $expectedSection = $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP');
        $expectedComponent = $section->fresh()->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');
        app(PageBuilderBlockService::class)->removeBlock($home, $section->fresh(), $actor, $expectedSection, $expectedComponent);

        $this->assertTrue($page->sections()->whereKey($section->id)->exists());
        $this->assertNotNull(ThemeSection::query()->whereKey($section->id)->first());
    }

    public function test_place_reusable_validates_same_theme_and_reusable_flag(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $other = $this->makeTheme();
        $home = $this->makeTemplate($theme, 'home');
        $page = $this->makeTemplate($theme, 'page');
        $foreign = $this->makeTemplate($other, 'home');
        $service = app(PageBuilderBlockService::class);

        $reusable = app(ThemeSectionService::class)->createAndPlace($home, ['is_reusable' => true], $actor);
        app(ThemeComponentService::class)->create($reusable, 'cta_button', $this->ctaConfig(), $actor);

        $service->placeReusable($page, $reusable->fresh(), $actor);
        $this->assertTrue($page->fresh()->sections()->whereKey($reusable->id)->exists());

        $alien = app(ThemeSectionService::class)->createAndPlace($foreign, ['is_reusable' => true], $actor);
        app(ThemeComponentService::class)->create($alien, 'cta_button', $this->ctaConfig(), $actor);

        try {
            $service->placeReusable($page, $alien->fresh(), $actor);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('cross_theme_section', $e->reason);
        }

        $plain = app(ThemeSectionService::class)->createAndPlace($home, ['is_reusable' => false], $actor);
        app(ThemeComponentService::class)->create($plain, 'cta_button', $this->ctaConfig(), $actor);

        try {
            $service->placeReusable($page, $plain->fresh(), $actor);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('section_not_reusable', $e->reason);
        }
    }

    public function test_stale_remove_and_visibility_conflict(): void
    {
        $seeder = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $section = $service->addBlock($template, 'donation_cta', $this->ctaConfig(), $seeder);
        $stale = $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP');
        $staleComponent = $section->fresh()->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');

        $service->setVisibility($section->fresh(), false, $seeder, $stale);
        $freshAfterToggle = $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP');
        $this->assertNotSame($stale, $freshAfterToggle);

        try {
            $service->removeBlock($template, $section->fresh(), $seeder, $stale, $staleComponent);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('stale_edit', $e->reason);
        }

        $this->assertTrue($template->fresh()->sections()->whereKey($section->id)->exists());

        try {
            $service->setVisibility($section->fresh(), true, $seeder, $stale);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('stale_edit', $e->reason);
        }

        $this->assertFalse($section->fresh()->visible);
    }

    public function test_missing_or_null_stale_tokens_are_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $section = $service->addBlock($template, 'donation_cta', $this->ctaConfig(), $actor);

        try {
            $service->setVisibility($section->fresh(), false, $actor, null);
            $this->fail('Expected ThemeValidationException.');
        } catch (\TypeError|ThemeValidationException $e) {
            // A null token is fail-closed whether PHP's own type system or
            // the service's own guard rejects it — either way nothing may
            // be persisted.
        }
        $this->assertTrue($section->fresh()->visible);

        try {
            $service->removeBlock($template, $section->fresh(), $actor, $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP'), null);
            $this->fail('Expected ThemeValidationException (missing component token).');
        } catch (ThemeValidationException $e) {
            $this->assertSame('stale_edit', $e->reason);
        }
        $this->assertTrue($template->fresh()->sections()->whereKey($section->id)->exists());
    }

    public function test_stale_remove_detects_component_change_even_when_section_timestamp_is_current(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $section = $service->addBlock($template, 'donation_cta', $this->ctaConfig(), $actor);
        // Captured BEFORE the component is edited below — becomes stale for
        // the component specifically, while the Section's own row (visible/
        // layout_variant) never changes and its timestamp stays current.
        $staleComponentToken = $section->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');

        $componentExpected = $section->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');
        $service->updateBlockConfig($section->fresh(), array_merge($this->ctaConfig(), ['label' => 'Changed']), $actor, $componentExpected);

        $currentSectionToken = $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP');

        try {
            // The Section token is CURRENT (the Section itself never
            // changed) — only the Component changed underneath it. Remove
            // must still fail closed rather than trust the Section alone.
            $service->removeBlock($template, $section->fresh(), $actor, $currentSectionToken, $staleComponentToken);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('stale_edit', $e->reason);
        }

        $this->assertTrue($template->fresh()->sections()->whereKey($section->id)->exists());
        $this->assertSame('Changed', $section->fresh()->components()->first()->config['label']);
    }

    public function test_interleaving_advanced_debug_write_cannot_be_overwritten_by_stale_builder_update(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $section = $service->addBlock($template, 'donation_cta', $this->ctaConfig(), $actor);
        $component = $section->components()->first();
        $expectedByBuilder = $component->updated_at->format('Y-m-d\TH:i:s.uP');

        // Simulates an Advanced/Debug write landing between the operator
        // loading the Builder form and submitting it.
        app(ThemeComponentService::class)->update($component, array_merge($this->ctaConfig(), ['label' => 'Advanced edit']), $actor);

        try {
            $service->updateBlockConfig($section->fresh(), array_merge($this->ctaConfig(), ['label' => 'Stale Builder overwrite']), $actor, $expectedByBuilder);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('stale_edit', $e->reason);
        }

        $this->assertSame('Advanced edit', $section->fresh()->components()->first()->config['label']);
    }

    public function test_discriminator_override_cannot_escape_registry_curation(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $article = $this->makeTemplate($theme, 'article');

        // 'articles_news' is curated for the article canvas with
        // content_kind=article by default; overriding content_kind to
        // 'campaign' after merge must not smuggle in Campaign Grid (which
        // is not allowed on the article canvas) through an honest-looking
        // block key.
        try {
            app(PageBuilderBlockService::class)->addBlock($article, 'articles_news', [
                'content_kind' => 'campaign', 'limit' => 6, 'order' => 'latest',
            ], $actor);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('block_not_builder_eligible', $e->reason);
        }

        $this->assertSame(0, $article->fresh()->sections()->count());
    }

    public function test_editing_advanced_debug_only_component_through_builder_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        // navigation_menu_slot has no PageBuilderBlockRegistry entry at all
        // — created here exactly as Advanced/Debug's ThemeController would,
        // directly via the canonical service, bypassing Builder entirely.
        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        app(ThemeComponentService::class)->create($section, 'navigation_menu_slot', ['menu_code' => 'primary'], $actor);
        $expected = $section->fresh()->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');

        try {
            app(PageBuilderBlockService::class)->updateBlockConfig($section->fresh(), ['menu_code' => 'footer'], $actor, $expected);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('block_not_builder_eligible', $e->reason);
        }

        $this->assertSame('primary', $section->fresh()->components()->first()->config['menu_code']);
    }

    public function test_duplicating_advanced_debug_only_component_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        // 'image' has no PageBuilderBlockRegistry entry either — another
        // Advanced/Debug-only type.
        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        app(ThemeComponentService::class)->create($section, 'image', [
            'source' => 'theme_asset', 'theme_asset_ulid' => str_repeat('A', 25).'1', 'alt_text' => 'Alt',
        ], $actor);

        try {
            app(PageBuilderBlockService::class)->duplicateBlock($template, $section->fresh(), $actor);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('block_not_builder_eligible', $e->reason);
        }

        $this->assertSame(1, $template->fresh()->sections()->count());
    }

    public function test_place_reusable_into_forbidden_canvas_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $home = $this->makeTemplate($theme, 'home');
        $article = $this->makeTemplate($theme, 'article');

        // Campaign content_list is only allowed on home/page — never on the
        // article canvas — even when reused via an existing reusable
        // Section rather than freshly added.
        $reusable = app(ThemeSectionService::class)->createAndPlace($home, ['is_reusable' => true], $actor);
        app(ThemeComponentService::class)->create($reusable, 'content_list', [
            'content_kind' => 'campaign', 'limit' => 6, 'order' => 'latest',
        ], $actor);

        try {
            app(PageBuilderBlockService::class)->placeReusable($article, $reusable->fresh(), $actor);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('block_not_builder_eligible', $e->reason);
        }

        $this->assertFalse($article->fresh()->sections()->whereKey($reusable->id)->exists());
    }

    public function test_add_and_update_with_real_foreign_asset_rejected(): void
    {
        Storage::fake(config('theme.disk'));
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $other = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $foreignAsset = app(ThemeAssetService::class)->upload(
            $other, UploadedFile::fake()->image('logo.png', 100, 100), $actor
        );

        try {
            $service->addBlock($template, 'hero', ['headline' => 'Hi', 'background_theme_asset_ulid' => $foreignAsset->ulid], $actor);
            $this->fail('Expected ThemeValidationException (add).');
        } catch (ThemeValidationException $e) {
            $this->assertSame('cross_theme_asset', $e->reason);
        }
        $this->assertSame(0, $template->fresh()->sections()->count());

        $section = $service->addBlock($template, 'hero', ['headline' => 'Hi'], $actor);
        $expected = $section->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');

        try {
            $service->updateBlockConfig($section->fresh(), ['headline' => 'Hi', 'background_theme_asset_ulid' => $foreignAsset->ulid], $actor, $expected);
            $this->fail('Expected ThemeValidationException (update).');
        } catch (ThemeValidationException $e) {
            $this->assertSame('cross_theme_asset', $e->reason);
        }
        $this->assertNull($section->fresh()->components()->first()->config['background_theme_asset_ulid'] ?? null);
    }

    public function test_duplicating_advanced_debug_component_with_foreign_asset_rejected(): void
    {
        Storage::fake(config('theme.disk'));
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $other = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $foreignAsset = app(ThemeAssetService::class)->upload(
            $other, UploadedFile::fake()->image('logo.png', 100, 100), $actor
        );

        // Advanced/Debug (ThemeComponentService directly) does not enforce
        // Builder's same-theme asset ownership rule, so this creation itself
        // succeeds — the invariant under test is that Builder's duplicate
        // path refuses to copy it.
        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        app(ThemeComponentService::class)->create($section, 'hero', [
            'headline' => 'Hi', 'background_theme_asset_ulid' => $foreignAsset->ulid,
        ], $actor);

        try {
            app(PageBuilderBlockService::class)->duplicateBlock($template, $section->fresh(), $actor);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('cross_theme_asset', $e->reason);
        }

        $this->assertSame(1, $template->fresh()->sections()->count());
    }

    public function test_non_active_asset_status_rejected_and_active_asset_succeeds(): void
    {
        Storage::fake(config('theme.disk'));
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $assetService = app(ThemeAssetService::class);

        $archivedAsset = $assetService->upload($theme, UploadedFile::fake()->image('archived.png', 100, 100), $actor);
        $assetService->archive($archivedAsset, $actor);

        try {
            app(PageBuilderBlockService::class)->addBlock($template, 'hero', [
                'headline' => 'Hi', 'background_theme_asset_ulid' => $archivedAsset->fresh()->ulid,
            ], $actor);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('cross_theme_asset', $e->reason);
        }
        $this->assertSame(0, $template->fresh()->sections()->count());

        $activeAsset = $assetService->upload($theme, UploadedFile::fake()->image('active.png', 100, 100), $actor);

        $section = app(PageBuilderBlockService::class)->addBlock($template, 'hero', [
            'headline' => 'Hi', 'background_theme_asset_ulid' => $activeAsset->ulid,
        ], $actor);

        $this->assertSame($activeAsset->ulid, $section->components()->first()->config['background_theme_asset_ulid']);
    }

    public function test_editing_multi_component_section_fails_closed(): void
    {
        $this->expectException(ThemeValidationException::class);

        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        app(ThemeComponentService::class)->create($section, 'cta_button', $this->ctaConfig(), $actor);
        app(ThemeComponentService::class)->create($section, 'cta_button', $this->ctaConfig(), $actor);

        // Rejected by resolveSingleComponent() before any token is consulted
        // — the exact value is irrelevant to this assertion.
        app(PageBuilderBlockService::class)->updateBlockConfig($section->fresh(), $this->ctaConfig(), $actor, '2020-01-01T00:00:00.000000+00:00');
    }

    // ================================================================
    // RA-03 — technical (non-Builder-managed) Sections are never removable
    // through Page Builder, using the SAME canonical eligibility check as
    // persistence (isSectionBuilderManaged()), not a naive component-count
    // heuristic.
    // ================================================================

    public function test_multi_component_technical_section_remove_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        app(ThemeComponentService::class)->create($section, 'cta_button', $this->ctaConfig(), $actor);
        app(ThemeComponentService::class)->create($section, 'cta_button', $this->ctaConfig(), $actor);

        try {
            app(PageBuilderBlockService::class)->removeBlock(
                $template, $section->fresh(), $actor,
                $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP')
            );
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('technical_section_not_removable', $e->reason);
        }

        $this->assertTrue($template->fresh()->sections()->whereKey($section->id)->exists());
        $this->assertSame(2, $section->fresh()->components()->count());
    }

    public function test_single_component_technical_navigation_menu_slot_remove_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        app(ThemeComponentService::class)->create($section, 'navigation_menu_slot', ['menu_code' => 'primary'], $actor);

        try {
            app(PageBuilderBlockService::class)->removeBlock(
                $template, $section->fresh(), $actor,
                $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP'),
                $section->fresh()->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP')
            );
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('technical_section_not_removable', $e->reason);
        }

        $this->assertTrue($template->fresh()->sections()->whereKey($section->id)->exists());
        $this->assertSame('primary', $section->fresh()->components()->first()->config['menu_code']);
    }

    public function test_single_component_technical_image_remove_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        app(ThemeComponentService::class)->create($section, 'image', [
            'source' => 'theme_asset', 'theme_asset_ulid' => str_repeat('A', 25).'1', 'alt_text' => 'Alt',
        ], $actor);

        try {
            app(PageBuilderBlockService::class)->removeBlock(
                $template, $section->fresh(), $actor,
                $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP'),
                $section->fresh()->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP')
            );
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('technical_section_not_removable', $e->reason);
        }

        $this->assertTrue($template->fresh()->sections()->whereKey($section->id)->exists());
    }

    public function test_direct_service_bypass_of_technical_remove_guard_rejected(): void
    {
        // Even a caller invoking the service directly (bypassing any UI
        // hidden-button convention) with a technically well-formed,
        // currently-correct token cannot remove a technical Section — the
        // guard is unconditional, not merely a stale-edit check.
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        app(ThemeComponentService::class)->create($section, 'navigation_menu_slot', ['menu_code' => 'footer'], $actor);
        $freshSection = $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP');
        $freshComponent = $section->fresh()->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');

        try {
            app(PageBuilderBlockService::class)->removeBlock($template, $section->fresh(), $actor, $freshSection, $freshComponent);
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('technical_section_not_removable', $e->reason);
        }

        $this->assertTrue($template->fresh()->sections()->whereKey($section->id)->exists());
        $this->assertSame(1, $section->fresh()->components()->count());
        $this->assertTrue($template->fresh()->sections()->whereKey($section->id)->exists());
    }

    public function test_normal_builder_managed_section_remains_removable_with_correct_revisions(): void
    {
        // Confirms RA-03's new guard does not regress ordinary Builder
        // block removal — see also test_remove_block_detaches_and_deletes_non_reusable_section.
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $section = $service->addBlock($template, 'donation_cta', $this->ctaConfig(), $actor);
        $this->assertTrue($service->isSectionBuilderManaged($section->fresh()));

        $service->removeBlock(
            $template, $section->fresh(), $actor,
            $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP'),
            $section->fresh()->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP')
        );

        $this->assertSame(0, $template->fresh()->sections()->count());
    }
}
