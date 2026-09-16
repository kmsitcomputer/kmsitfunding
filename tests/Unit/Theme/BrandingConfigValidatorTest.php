<?php

namespace Tests\Unit\Theme;

use App\Services\Theme\BrandingConfigValidator;
use App\Services\Theme\Exceptions\ThemeValidationException;
use Tests\TestCase;

/**
 * IMP-006 — branding token validation coverage (docs/implementation/
 * IMP-006-theme-engine.md section 16/21).
 */
class BrandingConfigValidatorTest extends TestCase
{
    private function validator(): BrandingConfigValidator
    {
        return new BrandingConfigValidator;
    }

    private function validTokens(): array
    {
        return [
            'primary' => '#111111',
            'secondary' => '#222222',
            'accent' => '#333333',
            'neutral_bg' => '#ffffff',
            'neutral_text' => '#000000',
        ];
    }

    public function test_accepts_a_complete_valid_token_set(): void
    {
        $this->validator()->assertValidColorTokens($this->validTokens());
        $this->addToAssertionCount(1);
    }

    public function test_rejects_an_unknown_token_name(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValidColorTokens([...$this->validTokens(), 'brand_new_color' => '#ffffff']);
    }

    public function test_rejects_a_non_hex_color_value(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValidColorTokens([...$this->validTokens(), 'primary' => 'red']);
    }

    public function test_rejects_a_css_injection_attempt_in_a_color_value(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValidColorTokens([...$this->validTokens(), 'primary' => '#111111; } body { display:none']);
    }

    public function test_accepts_an_allow_listed_font_family(): void
    {
        $this->validator()->assertValidFontFamily('serif');
        $this->addToAssertionCount(1);
    }

    public function test_rejects_a_font_family_outside_the_allow_list(): void
    {
        $this->expectException(ThemeValidationException::class);
        $this->validator()->assertValidFontFamily('url(https://evil.example/font.css)');
    }
}
