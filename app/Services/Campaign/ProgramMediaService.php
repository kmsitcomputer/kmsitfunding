<?php

namespace App\Services\Campaign;

use App\Models\Campaign\Program;
use App\Models\Campaign\ProgramMediaAsset;
use App\Models\Rbac\Principal;
use App\Services\Campaign\Exceptions\CampaignMediaValidationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * IMP-007 — Program asset upload intake + logical archive, mirroring
 * CampaignMediaService/ThemeAssetService's validation pipeline exactly.
 */
class ProgramMediaService
{
    public function __construct(private readonly CampaignAuditLogger $auditLogger) {}

    public function upload(Program $program, UploadedFile $file, Principal $uploader): ProgramMediaAsset
    {
        $mimeType = (string) $file->getMimeType();
        $extension = $this->resolveExtension($mimeType);

        if ($extension === null) {
            throw new CampaignMediaValidationException('unsupported_type', "MIME type '{$mimeType}' is not in the program media allow-list.");
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
        $directory = 'program/'.now()->format('Y/m');

        Storage::disk(config('campaign.disk'))->putFileAs($directory, $file, $storedFilename);

        return DB::transaction(function () use (
            $program, $ulid, $storedFilename, $file, $mimeType, $extension, $width, $height, $sha256, $uploader
        ) {
            $asset = new ProgramMediaAsset;
            $asset->forceFill([
                'ulid' => $ulid,
                'program_id' => $program->id,
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

            $this->auditLogger->recordProgramMediaUploaded($asset->id, [
                'asset_ulid' => $asset->ulid,
                'program_id' => $program->id,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size_bytes' => $asset->size_bytes,
                'sha256' => $sha256,
            ], $uploader);

            return $asset;
        });
    }

    public function archive(ProgramMediaAsset $asset, Principal $actor): ProgramMediaAsset
    {
        return DB::transaction(function () use ($asset, $actor) {
            $locked = ProgramMediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'ARCHIVED') {
                return $locked;
            }

            $locked->forceFill([
                'status' => 'ARCHIVED',
                'archived_at' => now(),
                'archived_by_principal_id' => $actor->id,
            ])->save();

            $this->auditLogger->recordProgramMediaArchived($locked->id, ['asset_ulid' => $locked->ulid], $actor);

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
