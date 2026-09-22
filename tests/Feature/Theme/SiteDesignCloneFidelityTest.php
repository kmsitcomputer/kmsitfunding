<?php

namespace Tests\Feature\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeComponent;
use App\Models\Theme\ThemeSection;
use App\Models\Theme\ThemeTemplate;
use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\SiteDesignCloneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CODEX-CR001C-02 — faithful ACTIVE → DRAFT cloning.
 * Proves: ACTIVE-only source enforcement, reusable section shared identity
 * across templates via canonical placement, visibility + order fidelity,
 * source immutability.
 */
class SiteDesignCloneFidelityTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function service(): SiteDesignCloneService
    {
        return app(SiteDesignCloneService::class);
    }

    public function test_active_source_clone_allowed(): void
    {
        config()->set('theme.disk', 'public');
        Storage::fake('public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedStatusTheme($actor, 'ACTIVE');

        $draft = $this->service()->cloneFromActive($source, $actor);

        $this->assertSame('DRAFT', $draft->status);
        $this->assertSame('ACTIVE', $source->fresh()->status);
    }

    /**
     * @dataProvider nonActiveStatuses
     */
    #[DataProvider('nonActiveStatuses')]
    public function test_non_active_source_rejected_without_side_effects(string $status): void
    {
        config()->set('theme.disk', 'public');
        Storage::fake('public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedStatusTheme($actor, $status);
        $filesBefore = Storage::disk('public')->allFiles();
        $themeCountBefore = Theme::count();

        try {
            $this->service()->cloneFromActive($source, $actor);
            $this->fail("Expected clone rejection for {$status} source.");
        } catch (ThemeValidationException $e) {
            $this->assertSame('source_not_active', $e->reason);
        }

        $this->assertSame($themeCountBefore, Theme::count());
        $this->assertSame($status, $source->fresh()->status);
        $this->assertSame($filesBefore, Storage::disk('public')->allFiles());
    }

    public static function nonActiveStatuses(): array
    {
        return [
            'DRAFT' => ['DRAFT'],
            'INACTIVE' => ['INACTIVE'],
            'ARCHIVED' => ['ARCHIVED'],
        ];
    }

    private function seedStatusTheme(Principal $actor, string $status): Theme
    {
        $theme = Theme::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Fidelity '.$status.' '.uniqid(),
            'slug' => 'fidelity-'.strtolower($status).'-'.uniqid(),
            'status' => $status,
            'is_system_default' => false,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);

        return $theme->fresh();
    }

    public function test_reusable_section_shared_across_templates_cloned_once(): void
    {
        config()->set('theme.disk', 'public');
        Storage::fake('public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedStatusTheme($actor, 'ACTIVE');

        $templateA = ThemeTemplate::create([
            'ulid' => (string) Str::ulid(),
            'theme_id' => $source->id,
            'slug' => 'tpl-a-'.uniqid(),
            'name' => 'A',
            'content_kind' => 'home',
        ]);
        $templateB = ThemeTemplate::create([
            'ulid' => (string) Str::ulid(),
            'theme_id' => $source->id,
            'slug' => 'tpl-b-'.uniqid(),
            'name' => 'B',
            'content_kind' => 'page',
        ]);

        $shared = ThemeSection::create([
            'ulid' => (string) Str::ulid(),
            'theme_id' => $source->id,
            'is_reusable' => true,
            'layout_variant' => 'wide',
            'visible' => true,
        ]);
        $templateA->sections()->attach($shared->id, ['position' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $templateB->sections()->attach($shared->id, ['position' => 1, 'created_at' => now(), 'updated_at' => now()]);

        ThemeComponent::create([
            'ulid' => (string) Str::ulid(),
            'theme_section_id' => $shared->id,
            'type' => 'hero',
            'config' => ['headline' => 'Shared'],
            'position' => 1,
        ]);

        $draft = $this->service()->cloneFromActive($source, $actor);

        $this->assertSame(1, $draft->sections()->count());

        $clonedSection = $draft->sections()->firstOrFail();
        $this->assertTrue((bool) $clonedSection->is_reusable);
        $this->assertSame('wide', $clonedSection->layout_variant);

        $cloneA = $draft->templates()->where('content_kind', 'home')->firstOrFail();
        $cloneB = $draft->templates()->where('content_kind', 'page')->firstOrFail();

        $this->assertSame([$clonedSection->id], $cloneA->sections()->pluck('theme_sections.id')->all());
        $this->assertSame([$clonedSection->id], $cloneB->sections()->pluck('theme_sections.id')->all());

        $this->assertSame(1, $clonedSection->components()->count());
        $this->assertSame('Shared', $clonedSection->components()->firstOrFail()->config['headline']);

        $this->assertSame(1, $source->fresh()->sections()->count());
        $this->assertSame($shared->id, $source->fresh()->sections()->firstOrFail()->id);
    }

    public function test_section_visibility_and_placement_order_preserved(): void
    {
        config()->set('theme.disk', 'public');
        Storage::fake('public');
        $actor = $this->makeUnauthorizedActor();
        $source = $this->seedStatusTheme($actor, 'ACTIVE');

        $template = ThemeTemplate::create([
            'ulid' => (string) Str::ulid(),
            'theme_id' => $source->id,
            'slug' => 'tpl-home-'.uniqid(),
            'name' => 'Home',
            'content_kind' => 'home',
        ]);

        $visibleFirst = ThemeSection::create([
            'ulid' => (string) Str::ulid(),
            'theme_id' => $source->id,
            'is_reusable' => false,
            'layout_variant' => 'first',
            'visible' => true,
        ]);
        $hiddenSecond = ThemeSection::create([
            'ulid' => (string) Str::ulid(),
            'theme_id' => $source->id,
            'is_reusable' => false,
            'layout_variant' => 'second',
            'visible' => false,
        ]);
        $template->sections()->attach($visibleFirst->id, ['position' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $template->sections()->attach($hiddenSecond->id, ['position' => 2, 'created_at' => now(), 'updated_at' => now()]);

        $draft = $this->service()->cloneFromActive($source, $actor);

        $clonedTemplate = $draft->templates()->where('content_kind', 'home')->firstOrFail();
        $ordered = $clonedTemplate->sections()->orderByPivot('position')->get();

        $this->assertSame(['first', 'second'], $ordered->pluck('layout_variant')->all());
        $this->assertTrue((bool) $ordered[0]->visible);
        $this->assertFalse((bool) $ordered[1]->visible);

        $this->assertTrue((bool) $visibleFirst->fresh()->visible);
        $this->assertFalse((bool) $hiddenSecond->fresh()->visible);
    }
}
