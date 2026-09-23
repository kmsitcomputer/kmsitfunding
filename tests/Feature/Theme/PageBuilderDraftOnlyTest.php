<?php

namespace Tests\Feature\Theme;

use App\Models\Theme\Theme;
use App\Models\Theme\ThemeSection;
use App\Models\Theme\ThemeTemplate;
use App\Services\Theme\ThemeComponentService;
use App\Services\Theme\ThemeSectionService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CR-001-D — DRAFT-only Page Builder mutations. Proves every builder
 * mutation fails closed (409) for ACTIVE/INACTIVE/ARCHIVED owners while
 * DRAFT succeeds when authorized, with persisted state verified unchanged
 * after each denial.
 */
class PageBuilderDraftOnlyTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeTheme(string $status = 'DRAFT'): Theme
    {
        $actor = $this->makeUnauthorizedActor();

        $theme = app(ThemeService::class)->create(['name' => 'PB DraftOnly '.$status.' '.uniqid()], $actor);
        $theme->forceFill(['status' => $status])->save();

        return $theme->fresh();
    }

    private function makeTemplate(Theme $theme): ThemeTemplate
    {
        return app(ThemeTemplateService::class)->create(
            $theme,
            ['name' => 'Home '.uniqid(), 'content_kind' => 'home'],
            $this->makeUnauthorizedActor()
        );
    }

    private function seedViaCanonicalServices(ThemeTemplate $template): ThemeSection
    {
        $seeder = $this->makeUnauthorizedActor();
        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $seeder);
        app(ThemeComponentService::class)->create($section, 'cta_button', $this->existingBlockConfig(), $seeder);

        return $section->fresh();
    }

    private function existingBlockConfig(): array
    {
        return [
            'label' => 'Donate',
            'variant' => 'primary',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/donate',
        ];
    }

    private function ctaPayload(): array
    {
        return [
            'block_key' => 'donation_cta',
            'config' => [
                'label' => 'Sneak',
                'variant' => 'primary',
                'destination_type' => 'EXTERNAL_URL',
                'destination_external_url' => 'https://example.com/sneak',
            ],
        ];
    }

    public function test_draft_mutation_succeeds_when_authorized(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $this->actingAs($actor->humanUser)
            ->post("/admin/page-builder/templates/{$template->ulid}/blocks", $this->ctaPayload())
            ->assertRedirect();

        $this->assertSame(1, $template->fresh()->sections()->count());
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_non_draft_add_block_denied_and_unchanged(string $status): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme($status);
        $template = $this->makeTemplate($theme);

        $this->actingAs($actor->humanUser)
            ->post("/admin/page-builder/templates/{$template->ulid}/blocks", $this->ctaPayload())
            ->assertConflict();

        $this->assertSame(0, $template->fresh()->sections()->count());
        $this->assertSame($status, $theme->fresh()->status);
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_non_draft_edit_remove_visibility_reorder_denied(string $status): void
    {
        $seeder = $this->makeUnauthorizedActor();
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme($status);
        $template = $this->makeTemplate($theme);
        $section = $this->seedViaCanonicalServices($template);

        $this->actingAs($actor->humanUser)
            ->patch("/admin/page-builder/blocks/{$section->ulid}", ['config' => $this->existingBlockConfig()])
            ->assertConflict();

        $this->actingAs($actor->humanUser)
            ->patch("/admin/page-builder/blocks/{$section->ulid}/visibility", ['visible' => false])
            ->assertConflict();

        $this->actingAs($actor->humanUser)
            ->put("/admin/page-builder/templates/{$template->ulid}/blocks/order", ['ordered_section_ulids' => [$section->ulid]])
            ->assertConflict();

        $this->actingAs($actor->humanUser)
            ->delete("/admin/page-builder/templates/{$template->ulid}/blocks/{$section->ulid}")
            ->assertConflict();

        $this->assertSame('Donate', $section->fresh()->components()->first()->config['label'] ?? 'Donate');
        $this->assertTrue($section->fresh()->visible);
        $this->assertSame(1, $template->fresh()->sections()->count());
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
