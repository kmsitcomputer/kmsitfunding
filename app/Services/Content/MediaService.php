<?php

namespace App\Services\Content;

use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMediaReference;
use App\Models\Rbac\Principal;
use App\Services\Content\Exceptions\MediaValidationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * IMP-005 — upload intake (section 19 validation pipeline) and logical
 * archive (section 19/26 flow 4). Physical purge belongs to
 * MediaCleanupService (a later slice, System-Principal-only per section 19
 * "cleanup actor / authority"). The section 19 attachment protocol (writing
 * cms_media_references rows when content embeds an asset) is NOT part of
 * this slice — it is wired into RevisionService/PublicationService in a
 * follow-on slice; this documented gap is the same one recorded in
 * RevisionService's own doc comment.
 */
class MediaService
{
    /**
     * @param  array{alt_text?:?string,caption?:?string}  $metadata
     */
    public function upload(UploadedFile $file, Principal $uploader, array $metadata = []): CmsMediaAsset
    {
        // Byte-sniffed via Symfony's finfo-backed getMimeType() — NEVER the
        // client-sent Content-Type (getClientMimeType()), per section 19
        // step 2 "client is never the security authority".
        $mimeType = (string) $file->getMimeType();

        if ($mimeType === 'image/svg+xml') {
            throw new MediaValidationException('svg_rejected', 'SVG uploads are rejected in v1 (section 19 step 7).');
        }

        $extension = $this->resolveExtension($mimeType);

        if ($extension === null) {
            throw new MediaValidationException('unsupported_type', "MIME type '{$mimeType}' is not in the allow-list.");
        }

        $maxBytes = config("media.allowed_types.{$extension}.max_bytes");

        if ($file->getSize() > $maxBytes) {
            throw new MediaValidationException(
                'file_too_large',
                "File size {$file->getSize()} exceeds the {$maxBytes}-byte bound for .{$extension}."
            );
        }

        $width = null;
        $height = null;

        if (in_array($extension, config('media.image_extensions'), true)) {
            [$width, $height] = $this->validateImage($file);
        }

        if ($extension === 'pdf') {
            $this->validatePdfHeader($file);
        }

        $sha256 = hash_file('sha256', $file->getRealPath());
        $ulid = (string) Str::ulid();
        $storedFilename = "{$ulid}.{$extension}";
        $directory = 'content/'.now()->format('Y/m');

        Storage::disk(config('media.disk'))->putFileAs($directory, $file, $storedFilename);

        $asset = new CmsMediaAsset;
        $asset->forceFill([
            'ulid' => $ulid,
            'disk' => config('media.disk'),
            'stored_filename' => $storedFilename,
            'original_filename' => $this->sanitizeOriginalFilename($file->getClientOriginalName()),
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size_bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt_text' => $metadata['alt_text'] ?? null,
            'caption' => $metadata['caption'] ?? null,
            'sha256' => $sha256,
            'status' => 'ACTIVE',
            'uploaded_by_principal_id' => $uploader->id,
        ]);
        $asset->save();

        return $asset;
    }

    /**
     * section 19 "Duplicate handling": computed, never auto-deduped — the
     * caller decides what to do with a hit.
     */
    public function findActiveDuplicate(string $sha256): ?CmsMediaAsset
    {
        return CmsMediaAsset::query()->where('sha256', $sha256)->where('status', 'ACTIVE')->first();
    }

    /**
     * Logical archive (section 19 "Media ARCHIVE"): NEVER blocked by
     * references — archiving ends attachability, it does not withdraw an
     * asset from content that already renders it. Idempotent on an
     * already-ARCHIVED asset (no re-transition, no duplicate audit event
     * once audit is wired).
     */
    public function archive(CmsMediaAsset $asset, Principal $actor): CmsMediaAsset
    {
        return DB::transaction(function () use ($asset, $actor) {
            $locked = CmsMediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'ARCHIVED') {
                return $locked;
            }

            if ($locked->status === 'PURGED') {
                throw new MediaValidationException(
                    'already_purged',
                    "Asset {$locked->id} is PURGED; there is nothing left to archive."
                );
            }

            // Tier 2: lock ACTIVE reference rows and recheck under the lock
            // (never trust a pre-lock read) — section 19/26 flow 4. Always
            // empty until the attachment-protocol wiring slice lands; kept
            // here now so that slice only has to ADD audit emission, not
            // this lock step.
            $hasActiveReferences = CmsMediaReference::query()
                ->where('media_asset_id', $locked->id)
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->exists();

            $locked->forceFill([
                'status' => 'ARCHIVED',
                'archived_at' => now(),
                'archived_by_principal_id' => $actor->id,
            ])->save();

            // $hasActiveReferences becomes the audit event's prior_references
            // (HAS_ACTIVE|NONE) once the audit slice lands — computed now so
            // that step is additive, not a redesign.
            return $locked;
        });
    }

    private function resolveExtension(string $mimeType): ?string
    {
        foreach (config('media.allowed_types', []) as $extension => $definition) {
            if ($definition['mime'] === $mimeType) {
                return $extension;
            }
        }

        return null;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function validateImage(UploadedFile $file): array
    {
        $dimensions = @getimagesize($file->getRealPath());

        if ($dimensions === false) {
            throw new MediaValidationException('image_decode_failed', 'File did not decode as a valid image.');
        }

        [$width, $height] = $dimensions;
        $max = config('media.max_image_dimension');

        if ($width > $max || $height > $max) {
            throw new MediaValidationException(
                'image_dimensions_too_large',
                "Image is {$width}x{$height}, exceeding the {$max}px bound (decompression-bomb guard)."
            );
        }

        return [$width, $height];
    }

    private function validatePdfHeader(UploadedFile $file): void
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $header = $handle !== false ? fread($handle, 4) : false;

        if ($handle !== false) {
            fclose($handle);
        }

        if ($header !== '%PDF') {
            throw new MediaValidationException('pdf_header_invalid', 'File does not start with a valid %PDF header.');
        }
    }

    private function sanitizeOriginalFilename(?string $name): string
    {
        $name = $name ?? 'upload';
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);

        return substr($name, 0, 255);
    }
}
