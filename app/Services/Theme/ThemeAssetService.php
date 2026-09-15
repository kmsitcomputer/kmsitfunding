<?php

namespace App\Services\Theme;

use App\Models\Rbac\Principal;
use App\Models\Theme\Theme;
use App\Models\Theme\ThemeAsset;
use App\Services\Theme\Exceptions\ThemeAssetValidationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * IMP-006 — theme asset upload intake + logical archive
 * (docs/implementation/IMP-006-theme-engine.md section 17), mirroring
 * App\Services\Content\MediaService's validation pipeline exactly:
 * byte-sniffed MIME via finfo (never client Content-Type), extension<->MIME
 * consistency, size ceilings, image decode verification, SVG rejected
 * outright (SVG is not even in the allow-list here), server-generated ULID
 * filenames, separate `theme/{yyyy}/{mm}` storage path.
 */
class ThemeAssetService
{
    public function __construct(private readonly ThemeAuditLogger $auditLogger) {}

    public function upload(Theme $theme, UploadedFile $file, Principal $uploader): ThemeAsset
    {
        $mimeType = (string) $file->getMimeType();
        $extension = $this->resolveExtension($mimeType);

        if ($extension === null) {
            throw new ThemeAssetValidationException('unsupported_type', "MIME type '{$mimeType}' is not in the theme asset allow-list.");
        }

        $maxBytes = config("theme.allowed_types.{$extension}.max_bytes");

        if ($file->getSize() > $maxBytes) {
            throw new ThemeAssetValidationException(
                'file_too_large',
                "File size {$file->getSize()} exceeds the {$maxBytes}-byte bound for .{$extension}."
            );
        }

        $dimensions = @getimagesize($file->getRealPath());

        if ($dimensions === false) {
            throw new ThemeAssetValidationException('image_decode_failed', 'File did not decode as a valid image.');
        }

        [$width, $height] = $dimensions;
        $maxDimension = config('theme.max_image_dimension');

        if ($width > $maxDimension || $height > $maxDimension) {
            throw new ThemeAssetValidationException(
                'image_dimensions_too_large',
                "Image is {$width}x{$height}, exceeding the {$maxDimension}px bound."
            );
        }

        $sha256 = hash_file('sha256', $file->getRealPath());
        $ulid = (string) Str::ulid();
        $storedFilename = "{$ulid}.{$extension}";
        $directory = 'theme/'.now()->format('Y/m');

        Storage::disk(config('theme.disk'))->putFileAs($directory, $file, $storedFilename);

        return DB::transaction(function () use (
            $theme, $ulid, $storedFilename, $file, $mimeType, $extension, $width, $height, $sha256, $uploader
        ) {
            $asset = new ThemeAsset;
            $asset->forceFill([
                'ulid' => $ulid,
                'theme_id' => $theme->id,
                'disk' => config('theme.disk'),
                'stored_filename' => $storedFilename,
                'original_filename' => $this->sanitizeOriginalFilename($file->getClientOriginalName()),
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size_bytes' => $file->getSize(),
                'width' => $width,
                'height' => $height,
                'sha256' => $sha256,
                'status' => 'ACTIVE',
                'uploaded_by_principal_id' => $uploader->id,
            ]);
            $asset->save();

            $this->auditLogger->recordAssetUploaded($asset->id, [
                'asset_ulid' => $asset->ulid,
                'theme_id' => $theme->id,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size_bytes' => $asset->size_bytes,
                'sha256' => $sha256,
            ], $uploader);

            return $asset;
        });
    }

    public function archive(ThemeAsset $asset, Principal $actor): ThemeAsset
    {
        return DB::transaction(function () use ($asset, $actor) {
            $locked = ThemeAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'ARCHIVED') {
                return $locked;
            }

            $locked->forceFill([
                'status' => 'ARCHIVED',
                'archived_at' => now(),
                'archived_by_principal_id' => $actor->id,
            ])->save();

            $this->auditLogger->recordAssetArchived($locked->id, [
                'asset_ulid' => $locked->ulid,
            ], $actor);

            return $locked;
        });
    }

    private function resolveExtension(string $mimeType): ?string
    {
        foreach (config('theme.allowed_types', []) as $extension => $definition) {
            if ($definition['mime'] === $mimeType) {
                return $extension;
            }
        }

        return null;
    }

    private function sanitizeOriginalFilename(?string $name): string
    {
        $name = $name ?? 'upload';
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);

        return substr($name, 0, 255);
    }
}
