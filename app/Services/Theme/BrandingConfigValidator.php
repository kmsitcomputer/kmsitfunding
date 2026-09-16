<?php

namespace App\Services\Theme;

use App\Services\Theme\Exceptions\ThemeValidationException;
use Illuminate\Support\Facades\Validator;

/**
 * IMP-006 — validates a BrandingConfig's `color_tokens` against the CLOSED
 * token-name set, and `font_family` against a pre-approved allow-list
 * (docs/implementation/IMP-006-theme-engine.md section 16). Never accepts
 * an arbitrary open key-value CSS variable bag, and never accepts an
 * arbitrary @font-face URL.
 */
class BrandingConfigValidator
{
    private const COLOR_TOKENS = ['primary', 'secondary', 'accent', 'neutral_bg', 'neutral_text'];

    private const FONT_ALLOW_LIST = ['system', 'serif', 'mono'];

    /**
     * @param  array<string, mixed>  $colorTokens
     */
    public function assertValidColorTokens(array $colorTokens): void
    {
        $rules = [];

        foreach (self::COLOR_TOKENS as $token) {
            $rules[$token] = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        $unknown = array_diff(array_keys($colorTokens), self::COLOR_TOKENS);

        if ($unknown !== []) {
            throw new ThemeValidationException(
                'unknown_color_token',
                'Unknown color token(s): '.implode(', ', $unknown)
            );
        }

        $validator = Validator::make($colorTokens, $rules);

        if ($validator->fails()) {
            throw new ThemeValidationException(
                'invalid_color_tokens',
                'Color token validation failed: '.$validator->errors()->first()
            );
        }
    }

    public function assertValidFontFamily(string $fontFamily): void
    {
        if (! in_array($fontFamily, self::FONT_ALLOW_LIST, true)) {
            throw new ThemeValidationException(
                'invalid_font_family',
                "Font family '{$fontFamily}' is not in the pre-approved allow-list."
            );
        }
    }
}
