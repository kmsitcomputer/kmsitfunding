<?php

namespace App\Services\Content;

use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMediaReference;
use App\Models\Rbac\Principal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * IMP-005 — the only CMS path that removes bytes (docs/implementation/
 * IMP-005-cms.md section 19 "Media cleanup model"). Two independent cases:
 *
 * CASE A (reconcileOrphanFiles): a file exists on disk with no
 * cms_media_assets row (upload crashed before the row committed). No DB
 * row exists, so there is no state transition and no audit record — an
 * APPLICATION LOG entry only.
 *
 * CASE B (purgeUnreferencedAssets): an ARCHIVED asset with ZERO ACTIVE
 * references, past its grace period. Physical deletion happens BEFORE the
 * DB transition (section 19: "the file is removed BEFORE the DB evidence
 * changes, and the DB row is the retry capability") — a missing file on
 * retry is tolerated as success, never an error.
 *
 * Runs under the content.media_cleanup System Principal (seeded by
 * CmsSystemPrincipalSeeder) — bounded to content.archive only, never
 * content.publish/content.update, never a Human role impersonated.
 */
class MediaCleanupService
{
    private const ULID_FILENAME_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}\.[a-z0-9]+$/';

    public function __construct(private readonly ContentAuditLogger $auditLogger) {}

    /**
     * @return array{scanned:int,removed:int,failed:int,refused:int}
     */
    public function reconcileOrphanFiles(): array
    {
        $disk = Storage::disk(config('media.disk'));
        $now = now();
        $summary = ['scanned' => 0, 'removed' => 0, 'failed' => 0, 'refused' => 0];

        foreach ([$now->copy(), $now->copy()->subMonth()] as $month) {
            $directory = 'content/'.$month->format('Y/m');

            foreach ($disk->files($directory) as $path) {
                $summary['scanned']++;
                $filename = basename($path);

                if (preg_match(self::ULID_FILENAME_PATTERN, $filename) !== 1) {
                    $summary['refused']++;
                    logger()->warning('media_orphan_cleanup_refused_bad_name', ['path' => $path]);

                    continue;
                }

                if (CmsMediaAsset::query()->where('stored_filename', $filename)->exists()) {
                    continue;
                }

                $lastModified = $disk->lastModified($path);
                $ageHours = ($now->timestamp - $lastModified) / 3600;

                if ($ageHours < (int) config('media.orphan_grace_hours')) {
                    continue; // slow in-flight upload guard — not yet eligible
                }

                try {
                    $disk->delete($path);
                    $summary['removed']++;
                    logger()->info('media_orphan_file_removed', [
                        'path' => $path, 'size' => $disk->size($path) ?? null, 'mtime' => $lastModified,
                    ]);
                } catch (\Throwable $e) {
                    $summary['failed']++;
                    logger()->error('media_orphan_cleanup_failed', ['path' => $path, 'error' => $e->getMessage()]);
                }
            }
        }

        return $summary;
    }

    /**
     * @return array{purged:int,refused:int,failed:int}
     */
    public function purgeUnreferencedAssets(Principal $systemActor, int $batchSize = 100): array
    {
        $graceCutoff = now()->subDays((int) config('media.purge_grace_days'));

        $candidates = CmsMediaAsset::query()
            ->where('status', 'ARCHIVED')
            ->where('archived_at', '<=', $graceCutoff)
            ->whereNotIn('id', CmsMediaReference::query()->where('status', 'ACTIVE')->select('media_asset_id'))
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        $summary = ['purged' => 0, 'refused' => 0, 'failed' => 0];

        foreach ($candidates as $candidate) {
            $summary[$this->purgeOne($candidate, $systemActor)]++;
        }

        return $summary;
    }

    /**
     * @return 'purged'|'refused'|'failed'
     */
    private function purgeOne(CmsMediaAsset $asset, Principal $systemActor): string
    {
        return DB::transaction(function () use ($asset, $systemActor) {
            $locked = CmsMediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'ARCHIVED') {
                return 'refused'; // already purged, or somehow reactivated — never happens in v1, defensive
            }

            // Tier 2, recheck under the lock — never trust the candidate query.
            $hasActiveReferences = CmsMediaReference::query()
                ->where('media_asset_id', $locked->id)
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->exists();

            if ($hasActiveReferences) {
                return 'refused'; // media_referenced
            }

            $graceCutoff = now()->subDays((int) config('media.purge_grace_days'));

            if ($locked->archived_at === null || $locked->archived_at->gt($graceCutoff)) {
                return 'refused'; // grace period not yet elapsed under the re-read
            }

            $path = $this->storedPath($locked);
            $disk = Storage::disk($locked->disk);

            try {
                // Flysystem's delete() is idempotent for a missing file (no
                // exception on "already gone") — exactly the tolerance
                // section 19 requires for a self-healing retry after an
                // unlink-succeeded-but-commit-failed prior run.
                $disk->delete($path);
            } catch (\Throwable $e) {
                $locked->forceFill([
                    'purge_attempts' => $locked->purge_attempts + 1,
                    'last_purge_error' => substr($e->getMessage(), 0, 511),
                ])->save();

                logger()->error('media_purge_failed', ['asset_id' => $locked->id, 'path' => $path, 'error' => $e->getMessage()]);

                return 'failed';
            }

            $previouslyArchivedBy = $locked->archived_by_principal_id;

            $locked->forceFill([
                'status' => 'PURGED',
                'purged_at' => now(),
            ])->save();

            $this->auditLogger->recordMediaPurged($locked->id, [
                'asset_ulid' => $locked->ulid,
                'purge_attempts' => $locked->purge_attempts,
                'active_references_verified_absent' => 1,
                'previously_archived_by_principal_id' => $previouslyArchivedBy,
                'system_operation' => 'content.media_cleanup',
            ], $systemActor);

            return 'purged';
        });
    }

    private function storedPath(CmsMediaAsset $asset): string
    {
        return 'content/'.$asset->created_at->format('Y/m').'/'.$asset->stored_filename;
    }
}
