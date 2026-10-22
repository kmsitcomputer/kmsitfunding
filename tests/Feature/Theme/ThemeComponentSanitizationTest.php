<?php

namespace Tests\Feature\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\ThemeSection;
use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\ThemeComponentService;
use App\Services\Theme\ThemeSectionService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

class ThemeComponentSanitizationTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private Principal $actor;

    private ThemeSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = $this->makeAuthorizedActor();
        $theme = app(ThemeService::class)->create(['name' => 'Custom HTML boundary'], $this->actor);
        $template = app(ThemeTemplateService::class)->create(
            $theme, ['name' => 'Home', 'content_kind' => 'home'], $this->actor
        );
        $this->section = app(ThemeSectionService::class)->createAndPlace($template, [], $this->actor);
    }

    public static function unsafeBodies(): array
    {
        return [
            'script' => ['<p>Hi</p><script>alert(1)</script>'],
            'javascript URL' => ['<a href="javascript:alert(1)">Click</a>'],
        ];
    }

    public static function acceptedBodies(): array
    {
        $safe = '<p class="intro">Hello <strong>world</strong> <a href="https://example.com">Read</a></p>';

        return [
            'safe markup preserved' => [$safe, $safe],
            'event handlers removed' => [
                '<p onclick="alert(1)">Hello <strong onmouseover="alert(2)">world</strong></p>',
                '<p>Hello <strong>world</strong></p>',
            ],
        ];
    }

    #[DataProvider('unsafeBodies')]
    public function test_canonical_create_rejects_unsafe_html_without_persistence(string $html): void
    {
        try {
            app(ThemeComponentService::class)->create($this->section, 'rich_text', $this->config($html), $this->actor);
            $this->fail('Unsafe HTML must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('body_html', $e->errors());
        }

        $this->assertDatabaseCount('theme_components', 0);
    }

    #[DataProvider('unsafeBodies')]
    public function test_canonical_update_rejects_unsafe_html_without_overwriting(string $html): void
    {
        $service = app(ThemeComponentService::class);
        $component = $service->create($this->section, 'rich_text', $this->config('<p>Original</p>'), $this->actor);
        $before = $component->fresh()->getAttributes();

        try {
            $service->update($component, $this->config($html), $this->actor);
            $this->fail('Unsafe HTML must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('body_html', $e->errors());
        }

        $this->assertSame($before, $component->fresh()->getAttributes());
        $this->assertDatabaseCount('theme_components', 1);
    }

    #[DataProvider('acceptedBodies')]
    public function test_canonical_create_persists_only_sanitized_markup(string $html, string $expected): void
    {
        $component = app(ThemeComponentService::class)->create($this->section, 'rich_text', $this->config($html), $this->actor);

        $this->assertSame($expected, trim($component->fresh()->config['body_html']));
        $this->assertSame('custom_html', $component->fresh()->config['source']);
        $this->assertDatabaseCount('theme_components', 1);
    }

    #[DataProvider('acceptedBodies')]
    public function test_canonical_update_persists_only_sanitized_markup(string $html, string $expected): void
    {
        $service = app(ThemeComponentService::class);
        // Switching from caption must use the incoming source and the stored type.
        $component = $service->create($this->section, 'rich_text', ['source' => 'caption', 'caption' => 'Original'], $this->actor);

        $service->update($component, $this->config($html), $this->actor);

        $this->assertSame($expected, trim($component->fresh()->config['body_html']));
        $this->assertSame('custom_html', $component->fresh()->config['source']);
        $this->assertDatabaseCount('theme_components', 1);
    }

    #[DataProvider('unsafeBodies')]
    public function test_advanced_http_create_rejects_unsafe_html_without_persistence(string $html): void
    {
        $this->actingAs($this->actor->humanUser)
            ->postJson(route('theme.components.create', $this->section), [
                'type' => 'rich_text', 'config' => $this->config($html),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body_html');

        $this->assertDatabaseCount('theme_components', 0);
    }

    #[DataProvider('unsafeBodies')]
    public function test_advanced_http_update_rejects_unsafe_html_without_overwriting(string $html): void
    {
        $component = app(ThemeComponentService::class)->create($this->section, 'rich_text', $this->config('<p>Original</p>'), $this->actor);
        $before = $component->fresh()->getAttributes();

        $this->actingAs($this->actor->humanUser)
            ->patchJson(route('theme.components.update', $component), ['config' => $this->config($html)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body_html');

        $this->assertSame($before, $component->fresh()->getAttributes());
        $this->assertDatabaseCount('theme_components', 1);
    }

    #[DataProvider('acceptedBodies')]
    public function test_advanced_http_create_persists_only_sanitized_markup(string $html, string $expected): void
    {
        $this->actingAs($this->actor->humanUser)
            ->postJson(route('theme.components.create', $this->section), [
                'type' => 'rich_text', 'config' => $this->config($html),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('theme_components', 1);
        $this->assertSame($expected, trim($this->section->components()->sole()->config['body_html']));
    }

    #[DataProvider('acceptedBodies')]
    public function test_advanced_http_update_persists_only_sanitized_markup(string $html, string $expected): void
    {
        $component = app(ThemeComponentService::class)->create($this->section, 'rich_text', $this->config('<p>Original</p>'), $this->actor);

        $this->actingAs($this->actor->humanUser)
            ->patchJson(route('theme.components.update', $component), ['config' => $this->config($html)])
            ->assertRedirect();

        $this->assertSame($expected, trim($component->fresh()->config['body_html']));
        $this->assertDatabaseCount('theme_components', 1);
    }

    public static function invalidConfigs(): array
    {
        return [
            'missing body' => [['source' => 'custom_html']],
            'null body' => [['source' => 'custom_html', 'body_html' => null]],
            'non-string body' => [['source' => 'custom_html', 'body_html' => ['<p>Hi</p>']]],
            'empty after sanitization' => [['source' => 'custom_html', 'body_html' => '<!-- comment only -->']],
        ];
    }

    #[DataProvider('invalidConfigs')]
    public function test_invalid_config_cannot_be_created_or_overwrite_existing_content(array $config): void
    {
        $service = app(ThemeComponentService::class);
        try {
            $service->create($this->section, 'rich_text', $config, $this->actor);
            $this->fail('Invalid custom HTML config must be rejected.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('invalid_component_config', $e->reason);
        }
        $this->assertDatabaseCount('theme_components', 0);

        $component = $service->create($this->section, 'rich_text', $this->config('<p>Original</p>'), $this->actor);
        $before = $component->fresh()->getAttributes();
        try {
            $service->update($component, $config, $this->actor);
            $this->fail('Invalid custom HTML config must be rejected.');
        } catch (ThemeValidationException $e) {
            $this->assertSame('invalid_component_config', $e->reason);
        }
        $this->assertSame($before, $component->fresh()->getAttributes());
    }

    public static function unrelatedConfigs(): array
    {
        return [
            'caption' => ['rich_text', ['source' => 'caption', 'caption' => '<strong>Plain caption</strong>']],
            'CMS content' => ['rich_text', ['source' => 'cms_content', 'content_kind' => 'page', 'content_ulid' => '01ARZ3NDEKTSV4RRFFQ69G5FAV']],
            'hero' => ['hero', ['headline' => '<strong>Plain headline</strong>']],
        ];
    }

    #[DataProvider('unrelatedConfigs')]
    public function test_unrelated_sources_and_types_are_unchanged(string $type, array $config): void
    {
        $service = app(ThemeComponentService::class);
        $component = $service->create($this->section, $type, $config, $this->actor);
        $this->assertSame($config, $component->fresh()->config);

        $service->update($component, $config, $this->actor);
        $this->assertSame($config, $component->fresh()->config);
    }

    public function test_advanced_http_custom_html_still_requires_authorization(): void
    {
        $component = app(ThemeComponentService::class)->create($this->section, 'rich_text', $this->config('<p>Original</p>'), $this->actor);
        $before = $component->fresh()->getAttributes();
        $unauthorized = $this->makeUnauthorizedActor();
        $this->actingAs($unauthorized->humanUser);

        $this->postJson(route('theme.components.create', $this->section), [
            'type' => 'rich_text', 'config' => $this->config('<p>Changed</p>'),
        ])->assertForbidden();
        $this->patchJson(route('theme.components.update', $component), [
            'config' => $this->config('<p>Changed</p>'),
        ])->assertForbidden();

        $this->assertDatabaseCount('theme_components', 1);
        $this->assertSame($before, $component->fresh()->getAttributes());
    }

    private function config(string $html): array
    {
        return ['source' => 'custom_html', 'body_html' => $html];
    }
}
