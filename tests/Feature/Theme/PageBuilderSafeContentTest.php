<?php

namespace Tests\Feature\Theme;

use App\Models\Theme\Theme;
use App\Models\Theme\ThemeTemplate;
use App\Services\Theme\ComponentConfigValidator;
use App\Services\Theme\Exceptions\ThemeValidationException;
use App\Services\Theme\PageBuilderBlockService;
use App\Services\Theme\ThemeService;
use App\Services\Theme\ThemeTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * CR-001-D (HD-CR001D-01 APPROVED) — Safe Custom Content. Proves: script /
 * event-handler / javascript: / iframe content is rejected at write time,
 * oversized bodies rejected, sanitized bodies persist, validator accepts the
 * custom_html contract.
 */
class PageBuilderSafeContentTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeTheme(): Theme
    {
        $actor = $this->makeUnauthorizedActor();

        $theme = app(ThemeService::class)->create(['name' => 'PB Safe '.uniqid()], $actor);

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

    public function test_validator_accepts_custom_html_contract_fields(): void
    {
        app(ComponentConfigValidator::class)->assertValid('rich_text', [
            'source' => 'custom_html',
            'body_html' => '<p>Hello</p>',
        ]);
        $this->addToAssertionCount(1);
    }

    public function test_safe_markup_is_sanitized_and_persisted(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $section = app(PageBuilderBlockService::class)->addBlock($template, 'safe_custom_content', [
            'source' => 'custom_html',
            'body_html' => '<p class="intro">Hello <strong>world</strong></p>',
        ], $actor);

        $stored = $section->components()->first()->config['body_html'];

        $this->assertSame('<p class="intro">Hello <strong>world</strong></p>', trim($stored));
    }

    public function test_update_uses_canonical_sanitization_and_preserves_safe_markup(): void
    {
        $actor = $this->makeAuthorizedActor();
        $template = $this->makeTemplate($this->makeTheme());
        $service = app(PageBuilderBlockService::class);
        $section = $service->addBlock($template, 'safe_custom_content', [
            'source' => 'custom_html', 'body_html' => '<p>Original</p>',
        ], $actor);
        $expected = $section->components()->sole()->updated_at->format('Y-m-d\TH:i:s.uP');

        $service->updateBlockConfig($section, [
            'source' => 'custom_html',
            'body_html' => '<p onclick="alert(1)">Updated <em>content</em></p>',
        ], $actor, $expected);

        $this->assertSame('<p>Updated <em>content</em></p>', trim($section->components()->sole()->config['body_html']));
    }

    public function test_rejected_update_preserves_the_existing_block(): void
    {
        $actor = $this->makeAuthorizedActor();
        $template = $this->makeTemplate($this->makeTheme());
        $service = app(PageBuilderBlockService::class);
        $section = $service->addBlock($template, 'safe_custom_content', [
            'source' => 'custom_html', 'body_html' => '<p>Original</p>',
        ], $actor);
        $component = $section->components()->sole();
        $before = $component->getAttributes();
        $expected = $component->updated_at->format('Y-m-d\TH:i:s.uP');

        try {
            $service->updateBlockConfig($section, [
                'source' => 'custom_html', 'body_html' => '<script>alert(1)</script>',
            ], $actor, $expected);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('body_html', $e->errors());
        }

        $this->assertSame($before, $component->fresh()->getAttributes());
        $this->assertSame(1, $template->sections()->count());
    }

    public function test_script_element_rejected_at_write(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        try {
            app(PageBuilderBlockService::class)->addBlock($template, 'safe_custom_content', [
                'source' => 'custom_html',
                'body_html' => '<p>Hi</p><script>alert(1)</script>',
            ], $actor);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(0, $template->sections()->count());
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_event_handlers_stripped_from_allowed_tags(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        $section = app(PageBuilderBlockService::class)->addBlock($template, 'safe_custom_content', [
            'source' => 'custom_html',
            'body_html' => '<p onclick="alert(1)">Hi</p>',
        ], $actor);

        $stored = $section->components()->first()->config['body_html'];

        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertStringContainsString('Hi', $stored);
    }

    public function test_javascript_url_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        try {
            app(PageBuilderBlockService::class)->addBlock($template, 'safe_custom_content', [
                'source' => 'custom_html',
                'body_html' => '<a href="javascript:alert(1)">Click</a>',
            ], $actor);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException) {
            $this->assertSame(0, $template->sections()->count());
        }
    }

    public function test_iframe_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        try {
            app(PageBuilderBlockService::class)->addBlock($template, 'safe_custom_content', [
                'source' => 'custom_html',
                'body_html' => '<iframe src="https://example.com"></iframe>',
            ], $actor);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException) {
            $this->assertSame(0, $template->sections()->count());
        }
    }

    public function test_oversized_body_rejected(): void
    {
        $actor = $this->makeAuthorizedActor();
        $theme = $this->makeTheme();
        $template = $this->makeTemplate($theme);

        try {
            app(PageBuilderBlockService::class)->addBlock($template, 'safe_custom_content', [
                'source' => 'custom_html',
                'body_html' => '<p>'.str_repeat('a', 210 * 1024).'</p>',
            ], $actor);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException) {
            $this->assertSame(0, $template->sections()->count());
        }
    }

    public function test_cta_intent_enum_accepts_closed_values_only(): void
    {
        $base = [
            'label' => 'Give',
            'variant' => 'primary',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com',
        ];

        foreach (['general', 'donation', 'zakat'] as $intent) {
            app(ComponentConfigValidator::class)->assertValid('cta_button', array_merge($base, ['intent' => $intent]));
        }

        try {
            app(ComponentConfigValidator::class)->assertValid('cta_button', array_merge($base, ['intent' => 'anything']));
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_display_mode_enum_accepts_closed_values_only(): void
    {
        $base = ['content_kind' => 'campaign', 'limit' => 6, 'order' => 'latest'];

        foreach (['grid', 'carousel'] as $mode) {
            app(ComponentConfigValidator::class)->assertValid('content_list', array_merge($base, ['display_mode' => $mode]));
        }

        try {
            app(ComponentConfigValidator::class)->assertValid('content_list', array_merge($base, ['display_mode' => 'masonry']));
            $this->fail('Expected ThemeValidationException.');
        } catch (ThemeValidationException) {
            $this->addToAssertionCount(1);
        }
    }
}
