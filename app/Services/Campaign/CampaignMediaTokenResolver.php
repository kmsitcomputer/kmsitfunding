<?php

namespace App\Services\Campaign;

use App\Models\Campaign\CampaignMediaAsset;
use Illuminate\Support\Facades\Storage;

/**
 * IMP-007 — stored CampaignMediaAsset ULID -> approved public URL, mirroring
 * ThemeAssetTokenResolver exactly. Unknown/malformed/missing resolves to
 * null, never an exception.
 */
class CampaignMediaTokenResolver
{
    private const ULID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/';

    public function resolveUrl(string $token): ?string
    {
        if (preg_match(self::ULID_PATTERN, $token) !== 1) {
            return null;
        }

        $asset = CampaignMediaAsset::query()->where('ulid', $token)->first();

        if ($asset === null) {
            return null;
        }

        return Storage::disk($asset->disk)->url($this->storedPath($asset));
    }

    private function storedPath(CampaignMediaAsset $asset): string
    {
        return 'campaign/'.$asset->created_at->format('Y/m').'/'.$asset->stored_filename;
    }
}
