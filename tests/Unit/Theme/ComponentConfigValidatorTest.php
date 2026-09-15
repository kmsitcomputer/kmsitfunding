<?php

namespace Tests\Unit\Theme;

use App\Services\Theme\ComponentConfigValidator;
use App\Services\Theme\Exceptions\ThemeValidationException;
use Tests\TestCase;

/**
 * IMP-006 — Component config schema validation coverage
 * (docs/implementation/IMP-006-theme-engine.md section 11/21). The
 * adversarial rows here are the enforcement point for "Theme configuration
 * is data, never code."
 */
class ComponentConfigValidatorTest extends TestCase
{
    private function validator(): ComponentConfigValidator
    {
        return new ComponentConfigValidator;
    }

    public function test_rejects_an_unknown_component_type(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValid('custom_html_block', ['html' => '<script>alert(1)</script>']);
    }

    public function test_accepts_a_valid_hero_config(): void
    {
        $this->validator()->assertValid('hero', ['headline' => 'Welcome']);
        $this->addToAssertionCount(1);
    }

    public function test_rejects_a_hero_config_missing_the_required_headline(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValid('hero', ['subheading' => 'x']);
    }

    public function test_rejects_a_cta_button_with_an_unsafe_external_url_scheme(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValid('cta_button', [
            'label' => 'Click',
            'variant' => 'primary',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'javascript:alert(1)',
        ]);
    }

    public function test_accepts_a_cta_button_with_a_valid_https_url(): void
    {
        $this->validator()->assertValid('cta_button', [
            'label' => 'Click',
            'variant' => 'primary',
            'destination_type' => 'EXTERNAL_URL',
            'destination_external_url' => 'https://example.com',
        ]);
        $this->addToAssertionCount(1);
    }

    public function test_rejects_an_invalid_content_list_order_value(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValid('content_list', [
            'content_kind' => 'page', 'limit' => 6, 'order' => 'random',
        ]);
    }

    public function test_rejects_an_image_config_missing_required_alt_text(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValid('image', ['source' => 'theme_asset', 'theme_asset_ulid' => str_repeat('A', 26)]);
    }

    public function test_component_types_returns_exactly_the_closed_v1_set(): void
    {
        $this->assertEqualsCanonicalizing([
            'hero', 'rich_text', 'image', 'cta_button', 'content_list', 'stats', 'banner', 'card_grid', 'navigation_menu_slot',
        ], $this->validator()->componentTypes());
    }
}
