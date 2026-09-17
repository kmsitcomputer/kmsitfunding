<?php

namespace App\Services\Theme;

use App\Models\Theme\ThemeAsset;
use Illuminate\Support\Facades\Storage;

/**
 * IMP-006 — stored ThemeAsset ULID -> approved public URL, mirroring
 * App\Services\Content\MediaTokenResolver exactly but for the
 * administratively-separate theme_assets table (docs/implementation/
 * IMP-006-theme-engine.md section 17/20 "Fallback"). ACTIVE and ARCHIVED
 * both resolve (an archived theme asset still renders where already
 * placed); unknown/malformed/missing resolves to null, never an exception.
 */
class ThemeAssetTokenResolver
{
    private const ULID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/';

    public function resolveUrl(string $token): ?string
    {
        if (preg_match(self::ULID_PATTERN, $token) !== 1) {
            return null;
        }

        $asset = ThemeAsset::query()->where('ulid', $token)->first();

        if ($asset === null) {
            return null;
        }

        return Storage::disk($asset->disk)->url($this->storedPath($asset));
    }

    private function storedPath(ThemeAsset $asset): string
    {
        return 'theme/'.$asset->created_at->format('Y/m').'/'.$asset->stored_filename;
    }
}
