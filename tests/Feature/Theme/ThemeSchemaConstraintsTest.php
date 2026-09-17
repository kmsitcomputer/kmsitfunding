<?php

namespace Tests\Feature\Theme;

use App\Models\Theme\Theme;
use App\Models\Theme\ThemeActivation;
use App\Models\Theme\ThemeComponent;
use App\Models\Theme\ThemeSection;
use App\Models\Theme\ThemeTemplate;
use App\Services\Theme\ThemeComponentService;
use App\Services\Theme\ThemeSectionService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-006 — proves the concurrency-relevant constraints are real DATABASE
 * invariants, not merely application-level pre-checks (docs/implementation/
 * IMP-006-theme-engine.md section 24), mirroring IMP-005's own "UNIQUE, not
 * just a pre-check" discipline for path claims.
 */
class ThemeSchemaConstraintsTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    public function test_theme_activation_singleton_rejects_a_second_row_at_the_database_level(): void
    {
        $this->expectException(QueryException::class);

        ThemeActivation::query()->insert([
            'id' => 2,
            'active_theme_id' => null,
            'assigned_by_principal_id' => null,
            'assigned_at' => null,
            'updated_at' => now(),
        ]);
    }

    public function test_theme_component_position_uniqueness_is_enforced_by_the_database_not_only_the_service(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = app(ThemeService::class)->create(['name' => 'A'], $actor);
        $template = app(ThemeTemplateService::class)->create($theme, ['name' => 'Home', 'content_kind' => 'home'], $actor);
        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        app(ThemeComponentService::class)->create($section, 'hero', ['headline' => 'One'], $actor);

        // Bypass the service entirely and attempt a raw duplicate-position
        // insert directly — the UNIQUE(theme_section_id, position)
        // constraint, not application care, must reject it.
        $this->expectException(QueryException::class);

        ThemeComponent::query()->insert([
            'ulid' => (string) Str::ulid(),
            'theme_section_id' => $section->id,
            'type' => 'hero',
            'config' => json_encode(['headline' => 'Duplicate']),
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_theme_template_content_kind_uniqueness_is_enforced_by_the_database(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = app(ThemeService::class)->create(['name' => 'A'], $actor);
        app(ThemeTemplateService::class)->create($theme, ['name' => 'Home', 'content_kind' => 'home'], $actor);

        $this->expectException(QueryException::class);

        ThemeTemplate::query()->insert([
            'ulid' => (string) Str::ulid(),
            'theme_id' => $theme->id,
            'slug' => 'home-2',
            'name' => 'Home Again',
            'content_kind' => 'home',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_reusable_section_cannot_be_deleted_while_still_placed_in_a_template(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = app(ThemeService::class)->create(['name' => 'A'], $actor);
        $template = app(ThemeTemplateService::class)->create($theme, ['name' => 'Home', 'content_kind' => 'home'], $actor);
        $section = app(ThemeSectionService::class)->createAndPlace($template, ['is_reusable' => true], $actor);

        $this->expectException(QueryException::class);

        // RESTRICT on theme_template_sections.theme_section_id — a
        // reference-count guard enforced by the database, not merely
        // service-layer care.
        ThemeSection::query()->whereKey($section->id)->delete();
    }
}
