<?php

namespace App\Services\Content;

use App\Models\Cms\CmsMediaAsset;
use Illuminate\Support\Facades\Storage;

/**
 * IMP-005 — stored placeholder token -> validated asset -> approved public
 * URL (docs/implementation/IMP-005-cms.md section 20). Read-only; the ONLY
 * producer of media URLs in output — the renderer, never the editor, is
 * what turns a `data-media` token into a `src`.
 *
 * ACTIVE and ARCHIVED assets both resolve (section 19 MEDIA STATE EFFECT
 * TABLE: archiving does not withdraw an asset from content that already
 * renders it). Only PURGED — and an unknown/malformed token — resolve to
 * null, never an exception: a render path must degrade gracefully, unlike
 * a write path.
 */
class MediaTokenResolver
{
    private const ULID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/';

    public function resolveUrl(string $token): ?string
    {
        if (preg_match(self::ULID_PATTERN, $token) !== 1) {
            return null;
        }

        $asset = CmsMediaAsset::query()->where('ulid', $token)->first();

        if ($asset === null || $asset->status === 'PURGED') {
            return null;
        }

        return Storage::disk($asset->disk)->url($this->storedPath($asset));
    }

    private function storedPath(CmsMediaAsset $asset): string
    {
        return 'content/'.$asset->created_at->format('Y/m').'/'.$asset->stored_filename;
    }
}
