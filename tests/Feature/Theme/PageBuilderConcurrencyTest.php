<?php

namespace Tests\Feature\Theme;

use App\Models\Theme\Theme;
use App\Models\Theme\ThemeTemplate;
use App\Services\Theme\PageBuilderBlockService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CR-001-D — stale-edit / concurrency protection. Proves: matching timestamp
 * succeeds, stale timestamp fails closed (409), microsecond precision
 * survives the HTTP/JSON/ISO-8601 round-trip.
 */
class PageBuilderConcurrencyTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeTheme(): Theme
    {
        $actor = $this->makeUnauthorizedActor();

        $theme = app(ThemeService::class)->create(['name' => 'PB Concurrency '.uniqid()], $actor);

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

    private function ctaConfig(string $label = 'Donate'): array
    {
        return [
            'label' => $label,
            'variant' => 'primary',
            // Carried forward on every edit — see PageBuilderBlockServiceTest.
            'intent' => 'donation',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com/donate',
        ];
    }

    public function test_fresh_timestamp_edit_succeeds(): void
    {
        $seeder = $this->makeAuthorizedActor();
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $section = app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', $this->ctaConfig(), $seeder);
        $expected = $section->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');

        $this->actingAs($actor->humanUser)
            ->patch("/admin/page-builder/blocks/{$section->ulid}", [
                'config' => $this->ctaConfig('Give'),
                'expected_updated_at' => $expected,
            ])
            ->assertRedirect();

        $this->assertSame('Give', $section->fresh()->components()->first()->config['label']);
    }

    public function test_microsecond_timestamp_persists_and_distinguishes_writes(): void
    {
        $seeder = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $section = $service->addBlock($template, 'donation_cta', $this->ctaConfig(), $seeder);
        $first = $section->components()->first()->updated_at->format('Y-m-d H:i:s.u');
        $expected = $section->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');

        $service->updateBlockConfig($section->fresh(), $this->ctaConfig('Second'), $seeder, $expected);
        $second = $section->fresh()->components()->first()->updated_at->format('Y-m-d H:i:s.u');

        $this->assertMatchesRegularExpression('/\.\d{6}$/', $first);
        $this->assertMatchesRegularExpression('/\.\d{6}$/', $second);
        $this->assertNotSame('000000', substr($first, -6));
        $this->assertNotSame($first, $second);
    }

    public function test_stale_microsecond_timestamp_conflicts_without_overwriting(): void
    {
        $seeder = $this->makeAuthorizedActor();
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);
        $service = app(PageBuilderBlockService::class);

        $section = $service->addBlock($template, 'donation_cta', $this->ctaConfig(), $seeder);
        $stale = $section->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');
        $this->assertNotSame('000000', substr($stale, -13, 6));

        $service->updateBlockConfig($section->fresh(), $this->ctaConfig('Newer'), $seeder, $stale);
        $fresh = $section->fresh()->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');
        $this->assertNotSame($stale, $fresh);

        // RA-04/N-04: flashed validation error, not a raw 409 abort — see
        // PageBuilderController::update().
        $this->actingAs($actor->humanUser)
            ->patch("/admin/page-builder/blocks/{$section->ulid}", [
                'config' => $this->ctaConfig('Stale overwrite'),
                'expected_updated_at' => $stale,
            ])
            ->assertSessionHasErrors('config');

        $this->assertSame('Newer', $section->fresh()->components()->first()->config['label']);
    }

    public function test_microsecond_timestamp_round_trips_through_json(): void
    {
        $seeder = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $section = app(PageBuilderBlockService::class)->addBlock($template, 'donation_cta', $this->ctaConfig(), $seeder);
        $iso = $section->components()->first()->updated_at->format('Y-m-d\TH:i:s.uP');

        $decoded = json_decode(json_encode(['expected_updated_at' => $iso]), true);

        $this->assertSame($iso, $decoded['expected_updated_at']);
        $this->assertMatchesRegularExpression('/\.\d{6}/', $iso);
        $this->assertNotSame('000000', substr($iso, -13, 6));
    }
}
