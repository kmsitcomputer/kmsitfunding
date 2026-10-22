<?php

namespace Tests\Feature\Theme;

use App\Models\Theme\Theme;
use App\Models\Theme\ThemeSection;
use App\Models\Theme\ThemeTemplate;
use App\Services\Theme\PageBuilderBlockService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CR-001-D — reorder exact-set semantics. Proves: reorder success, persisted
 * ordering on re-fetch, duplicate IDs rejected, missing IDs rejected,
 * foreign-section IDs rejected, cross-template manipulation rejected.
 */
class PageBuilderReorderTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeTheme(string $status = 'DRAFT'): Theme
    {
        $actor = $this->makeUnauthorizedActor();

        $theme = app(ThemeService::class)->create(['name' => 'PB Reorder '.uniqid()], $actor);
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

    private function ctaConfig(string $label = 'Donate'): array
    {
        return [
            'label' => $label,
            'variant' => 'primary',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/donate',
        ];
    }

    /**
     * @return array<int, ThemeSection>
     */
    private function seedThree(ThemeTemplate $template): array
    {
        $seeder = $this->makeUnauthorizedActor();
        $service = app(PageBuilderBlockService::class);

        return [
            $service->addBlock($template, 'donation_cta', $this->ctaConfig('One'), $seeder),
            $service->addBlock($template, 'donation_cta', $this->ctaConfig('Two'), $seeder),
            $service->addBlock($template, 'donation_cta', $this->ctaConfig('Three'), $seeder),
        ];
    }

    public function test_reorder_reverses_and_persists_ordering(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        [$one, $two, $three] = $this->seedThree($template);

        $this->actingAs($actor->humanUser)
            ->put("/admin/page-builder/templates/{$template->ulid}/blocks/order", [
                'ordered_section_ulids' => [$three->ulid, $two->ulid, $one->ulid],
            ])
            ->assertRedirect();

        $ordered = $template->fresh()->sections()->orderByPivot('position')->pluck('theme_sections.ulid')->all();
        $this->assertSame([$three->ulid, $two->ulid, $one->ulid], $ordered);

        $refetched = $template->fresh()->sections()->orderByPivot('position')->pluck('theme_sections.ulid')->all();
        $this->assertSame($ordered, $refetched);
    }

    public function test_reorder_with_duplicate_ids_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        [$one, $two] = $this->seedThree($template);

        $before = $template->sections()->orderByPivot('position')->pluck('theme_sections.ulid')->all();

        $this->actingAs($actor->humanUser)
            ->put("/admin/page-builder/templates/{$template->ulid}/blocks/order", [
                'ordered_section_ulids' => [$one->ulid, $one->ulid, $two->ulid],
            ])
            ->assertSessionHasErrors('ordered_section_ulids');

        $this->assertSame($before, $template->fresh()->sections()->orderByPivot('position')->pluck('theme_sections.ulid')->all());
    }

    public function test_reorder_with_missing_ids_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        [$one, $two] = $this->seedThree($template);

        $this->actingAs($actor->humanUser)
            ->put("/admin/page-builder/templates/{$template->ulid}/blocks/order", [
                'ordered_section_ulids' => [$one->ulid, $two->ulid],
            ])
            ->assertSessionHasErrors('ordered_section_ulids');
    }

    public function test_reorder_with_foreign_section_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $other = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $foreign = $this->makeTemplate($other);
        [$one, $two, $three] = $this->seedThree($template);
        $alien = app(PageBuilderBlockService::class)->addBlock($foreign, 'donation_cta', $this->ctaConfig(), $this->makeUnauthorizedActor());

        $this->actingAs($actor->humanUser)
            ->put("/admin/page-builder/templates/{$template->ulid}/blocks/order", [
                'ordered_section_ulids' => [$one->ulid, $two->ulid, $alien->ulid],
            ])
            ->assertSessionHasErrors('ordered_section_ulids');

        $this->assertSame(3, $template->fresh()->sections()->count());
        $this->assertSame($three->ulid, $three->fresh()->ulid);
    }

    public function test_cross_template_remove_rejected(): void
    {
        $seeder = $this->makeUnauthorizedActor();
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $home = $this->makeTemplate($theme, 'home');
        $page = $this->makeTemplate($theme, 'page');

        $section = app(PageBuilderBlockService::class)->addBlock($home, 'donation_cta', $this->ctaConfig(), $seeder);

        $this->actingAs($actor->humanUser)
            ->delete("/admin/page-builder/templates/{$page->ulid}/blocks/{$section->ulid}", [
                'expected_section_updated_at' => $section->fresh()->updated_at->format('Y-m-d\TH:i:s.uP'),
            ])
            ->assertSessionHasErrors('section');

        $this->assertTrue($home->fresh()->sections()->whereKey($section->id)->exists());
    }
}
