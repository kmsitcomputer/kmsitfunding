<?php

namespace App\Models\Theme;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IMP-006 — one validated branding record per Theme (docs/implementation/
 * IMP-006-theme-engine.md section 16). `color_tokens` is validated against
 * the closed token-name set by App\Services\Theme\BrandingConfigValidator
 * before every save.
 */
class ThemeBrandingConfig extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'color_tokens' => 'array',
        ];
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function logoAsset(): BelongsTo
    {
        return $this->belongsTo(ThemeAsset::class, 'logo_theme_asset_id');
    }

    public function faviconAsset(): BelongsTo
    {
        return $this->belongsTo(ThemeAsset::class, 'favicon_theme_asset_id');
    }
}
