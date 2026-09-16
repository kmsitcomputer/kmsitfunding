<?php

namespace App\Services\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignMediaAsset;
use App\Models\Rbac\Principal;
use App\Services\Campaign\Exceptions\CampaignMediaValidationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * IMP-007 — Campaign asset upload intake + logical archive, mirroring
 * ThemeAssetService's validation pipeline exactly: byte-sniffed MIME via
 * finfo (never client Content-Type), extension<->MIME consistency, size
 * ceilings, image decode verification, SVG rejected outright (not in the
 * allow-list), server-generated ULID filenames, separate
 * `campaign/{yyyy}/{mm}` storage path.
 */
class CampaignMediaService
{
    public function __construct(private readonly CampaignAuditLogger $auditLogger) {}

    public function upload(Campaign $campaign, UploadedFile $file, Principal $uploader): CampaignMediaAsset
    {
        $mimeType = (string) $file->getMimeType();
        $extension = $this->resolveExtension($mimeType);

        if ($extension === null) {
            throw new CampaignMediaValidationException('unsupported_type', "MIME type '{$mimeType}' is not in the campaign media allow-list.");
        }

        $maxBytes = config("campaign.allowed_types.{$extension}.max_bytes");

        if ($file->getSize() > $maxBytes) {
            throw new CampaignMediaValidationException(
                'file_too_large',
                "File size {$file->getSize()} exceeds the {$maxBytes}-byte bound for .{$extension}."
            );
        }

        $dimensions = @getimagesize($file->getRealPath());

        if ($dimensions === false) {
            throw new CampaignMediaValidationException('image_decode_failed', 'File did not decode as a valid image.');
        }

        [$width, $height] = $dimensions;
        $maxDimension = config('campaign.max_image_dimension');

        if ($width > $maxDimension || $height > $maxDimension) {
            throw new CampaignMediaValidationException(
                'image_dimensions_too_large',
                "Image is {$width}x{$height}, exceeding the {$maxDimension}px bound."
            );
        }

        $sha256 = hash_file('sha256', $file->getRealPath());
        $ulid = (string) Str::ulid();
        $storedFilename = "{$ulid}.{$extension}";
        $directory = 'campaign/'.now()->format('Y/m');

        Storage::disk(config('campaign.disk'))->putFileAs($directory, $file, $storedFilename);

        return DB::transaction(function () use (
            $campaign, $ulid, $storedFilename, $file, $mimeType, $extension, $width, $height, $sha256, $uploader
        ) {
            $asset = new CampaignMediaAsset;
            $asset->forceFill([
                'ulid' => $ulid,
                'campaign_id' => $campaign->id,
                'disk' => config('campaign.disk'),
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

            $this->auditLogger->recordCampaignMediaUploaded($asset->id, [
                'asset_ulid' => $asset->ulid,
                'campaign_id' => $campaign->id,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size_bytes' => $asset->size_bytes,
                'sha256' => $sha256,
            ], $uploader);

            return $asset;
        });
    }

    public function archive(CampaignMediaAsset $asset, Principal $actor): CampaignMediaAsset
    {
        return DB::transaction(function () use ($asset, $actor) {
            $locked = CampaignMediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'ARCHIVED') {
                return $locked;
            }

            $locked->forceFill([
                'status' => 'ARCHIVED',
                'archived_at' => now(),
                'archived_by_principal_id' => $actor->id,
            ])->save();

            $this->auditLogger->recordCampaignMediaArchived($locked->id, ['asset_ulid' => $locked->ulid], $actor);

            return $locked;
        });
    }

    private function resolveExtension(string $mimeType): ?string
    {
        foreach (config('campaign.allowed_types', []) as $extension => $definition) {
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
