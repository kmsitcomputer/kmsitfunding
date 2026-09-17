<?php

namespace Tests\Feature\Theme;

use App\Models\Theme\ThemeActivation;
use App\Models\Theme\ThemeComponent;
use App\Services\Theme\Exceptions\ThemeActivationConflictException;
use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\ThemeActivationService;
use App\Services\Theme\ThemeComponentService;
use App\Services\Theme\ThemeSectionService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-006 — Theme activation / singleton-switching coverage
 * (docs/implementation/IMP-006-theme-engine.md section 8/24), mirroring
 * IMP-005's PublicationService::setHomepage() test discipline.
 */
class ThemeActivationServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function service(): ThemeActivationService
    {
        return app(ThemeActivationService::class);
    }

    public function test_activate_sets_the_singleton_pointer_and_theme_status(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = app(ThemeService::class)->create(['name' => 'A'], $actor);

        $activated = $this->service()->activate($theme, $actor);

        $this->assertSame('ACTIVE', $activated->status);
        $this->assertSame($theme->id, ThemeActivation::find(1)->active_theme_id);
    }

    public function test_activating_a_second_theme_deactivates_the_first(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $themeA = app(ThemeService::class)->create(['name' => 'A'], $actor);
        $themeB = app(ThemeService::class)->create(['name' => 'B'], $actor);

        $this->service()->activate($themeA, $actor);
        $this->service()->activate($themeB, $actor);

        $this->assertSame('INACTIVE', $themeA->fresh()->status);
        $this->assertSame('ACTIVE', $themeB->fresh()->status);
        $this->assertSame($themeB->id, ThemeActivation::find(1)->active_theme_id);
    }

    public function test_activate_rejects_an_archived_theme(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = app(ThemeService::class)->create(['name' => 'A'], $actor);
        $theme->forceFill(['status' => 'ARCHIVED'])->save();

        $this->expectException(ThemeValidationException::class);
        $this->service()->activate($theme, $actor);
    }

    public function test_activate_rejects_a_stale_expected_active_theme(): void
    {
        // Mirrors PublicationService::setHomepage()'s identical convention:
        // a null expectation means "don't check" (matches a legitimate
        // first activation), so the conflict case exercised here is a
        // caller expecting a DIFFERENT non-null theme than what is
        // actually active — a genuinely stale read, not "nothing active".
        $actor = $this->makeUnauthorizedActor();
        $themeA = app(ThemeService::class)->create(['name' => 'A'], $actor);
        $themeB = app(ThemeService::class)->create(['name' => 'B'], $actor);
        $themeC = app(ThemeService::class)->create(['name' => 'C'], $actor);
        $this->service()->activate($themeA, $actor);

        $this->expectException(ThemeActivationConflictException::class);
        // Caller still thinks B is active, but A actually is — stale read.
        $this->service()->activate($themeC, $actor, expectedActiveThemeId: $themeB->id);
    }

    public function test_activation_re_validates_every_component_config_and_rejects_if_invalid(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = app(ThemeService::class)->create(['name' => 'A'], $actor);
        $template = app(ThemeTemplateService::class)->create($theme, ['name' => 'Home', 'content_kind' => 'home'], $actor);
        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        $component = app(ThemeComponentService::class)->create($section, 'hero', ['headline' => 'Hi'], $actor);

        // Simulate a schema tightening since the row was saved: corrupt the
        // stored config directly (bypassing the validator) to prove
        // activation re-checks it, not merely trusts the stored value.
        ThemeComponent::whereKey($component->id)->update(['config' => json_encode(['headline' => str_repeat('x', 300)])]);

        $this->expectException(ThemeValidationException::class);
        $this->service()->activate($theme, $actor);
    }

    public function test_activation_succeeds_when_every_component_config_is_valid(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $theme = app(ThemeService::class)->create(['name' => 'A'], $actor);
        $template = app(ThemeTemplateService::class)->create($theme, ['name' => 'Home', 'content_kind' => 'home'], $actor);
        $section = app(ThemeSectionService::class)->createAndPlace($template, [], $actor);
        app(ThemeComponentService::class)->create($section, 'hero', ['headline' => 'Welcome'], $actor);

        $activated = $this->service()->activate($theme, $actor);

        $this->assertSame('ACTIVE', $activated->status);
    }
}
