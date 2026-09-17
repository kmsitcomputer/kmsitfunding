<?php

namespace Tests\Feature\Theme;

use App\Models\Theme\Theme;
use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-006 — Theme identity CRUD coverage (docs/implementation/
 * IMP-006-theme-engine.md section 8).
 */
class ThemeServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function service(): ThemeService
    {
        return app(ThemeService::class);
    }

    public function test_create_sets_expected_defaults(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $theme = $this->service()->create(['name' => 'My Theme'], $actor);

        $this->assertSame('my-theme', $theme->slug);
        $this->assertSame('DRAFT', $theme->status);
        $this->assertFalse($theme->is_system_default);
    }

    public function test_create_rejects_a_duplicate_slug(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $this->service()->create(['name' => 'My Theme', 'slug' => 'shared'], $actor);

        $this->expectException(ThemeValidationException::class);
        $this->service()->create(['name' => 'Other', 'slug' => 'shared'], $actor);
    }

    public function test_update_changes_the_name(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->service()->create(['name' => 'Original'], $actor);

        $updated = $this->service()->update($theme, ['name' => 'Renamed'], $actor);

        $this->assertSame('Renamed', $updated->name);
    }

    public function test_update_rejects_editing_the_system_default_theme(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $default = Theme::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'System Default', 'slug' => 'system-default', 'status' => 'ACTIVE',
            'is_system_default' => true,
        ]);

        $this->expectException(ThemeValidationException::class);
        $this->service()->update($default, ['name' => 'Hacked'], $actor);
    }

    public function test_archive_transitions_a_draft_theme(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->service()->create(['name' => 'To Archive'], $actor);

        $archived = $this->service()->archive($theme, $actor);

        $this->assertSame('ARCHIVED', $archived->status);
    }

    public function test_archive_rejects_an_active_theme(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = $this->service()->create(['name' => 'Active'], $actor);
        $theme->forceFill(['status' => 'ACTIVE'])->save();

        $this->expectException(ThemeValidationException::class);
        $this->service()->archive($theme, $actor);
    }

    public function test_archive_rejects_the_system_default_theme(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $default = Theme::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'System Default', 'slug' => 'system-default-2', 'status' => 'DRAFT',
            'is_system_default' => true,
        ]);

        $this->expectException(ThemeValidationException::class);
        $this->service()->archive($default, $actor);
    }
}
