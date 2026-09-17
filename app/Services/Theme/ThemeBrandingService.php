<?php

namespace App\Services\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeAsset;
use App\Models\Theme\ThemeBrandingConfig;
use App\Services\Theme\Exceptions\ThemeValidationException;
use Illuminate\Support\Facades\DB;

/**
 * IMP-006 — one validated BrandingConfig per Theme (docs/implementation/
 * IMP-006-theme-engine.md section 16). `updateOrCreate`-style upsert
 * (a Theme has AT MOST one BrandingConfig, per the unique `theme_id` column).
 */
class ThemeBrandingService
{
    public function __construct(
        private readonly BrandingConfigValidator $validator,
        private readonly ThemeAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{color_tokens:array<string,string>,font_family?:string,logo_theme_asset_ulid?:?string,favicon_theme_asset_ulid?:?string}  $payload
     */
    public function save(Theme $theme, array $payload, Principal $actor): ThemeBrandingConfig
    {
        return DB::transaction(function () use ($theme, $payload, $actor) {
            $this->validator->assertValidColorTokens($payload['color_tokens']);

            $fontFamily = $payload['font_family'] ?? 'system';
            $this->validator->assertValidFontFamily($fontFamily);

            $logoAssetId = $this->resolveAssetId($theme, $payload['logo_theme_asset_ulid'] ?? null);
            $faviconAssetId = $this->resolveAssetId($theme, $payload['favicon_theme_asset_ulid'] ?? null);

            $branding = ThemeBrandingConfig::firstOrNew(['theme_id' => $theme->id]);
            $branding->color_tokens = $payload['color_tokens'];
            $branding->font_family = $fontFamily;
            $branding->logo_theme_asset_id = $logoAssetId;
            $branding->favicon_theme_asset_id = $faviconAssetId;
            $branding->save();

            $this->auditLogger->recordBrandingUpdated($branding->id, [
                'theme_id' => $theme->id,
                'fields_changed' => ['color_tokens', 'font_family'],
            ], $actor);

            return $branding;
        });
    }

    private function resolveAssetId(Theme $theme, ?string $assetUlid): ?int
    {
        if ($assetUlid === null) {
            return null;
        }

        $asset = ThemeAsset::where('ulid', $assetUlid)->where('theme_id', $theme->id)->first();

        if ($asset === null) {
            throw new ThemeValidationException('unknown_theme_asset', "Theme asset '{$assetUlid}' does not belong to this theme.");
        }

        if ($asset->status !== 'ACTIVE') {
            throw new ThemeValidationException('theme_asset_not_active', "Theme asset '{$assetUlid}' is not ACTIVE.");
        }

        return $asset->id;
    }
}
