<?php

namespace Tests\Feature\Cms;

use App\Models\Audit\AuditRecord;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Content\MediaCleanupService;
use App\Services\Content\MediaService;
use App\Services\Content\RevisionService;
use Database\Seeders\CmsSystemPrincipalSeeder;
use Database\Seeders\RbacPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 19 (MediaCleanupService) coverage — docs/implementation/
 * IMP-005-cms.md section 19 "Media cleanup model", cases A and B.
 */
class MediaCleanupServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('media.disk'));
        $this->seed(RbacPermissionSeeder::class);
        $this->seed(CmsSystemPrincipalSeeder::class);
    }

    private function cleanupPrincipal(): Principal
    {
        $systemPrincipal = SystemPrincipal::where('code', 'content.media_cleanup')->firstOrFail();

        return Principal::where('system_principal_id', $systemPrincipal->id)->firstOrFail();
    }

    private function service(): MediaCleanupService
    {
        return app(MediaCleanupService::class);
    }

    // --- Case A: orphan files ---

    public function test_orphan_file_older_than_grace_is_removed(): void
    {
        $disk = Storage::disk(config('media.disk'));
        $month = now()->format('Y/m');
        $disk->put("content/{$month}/01J9ZK3V7Q4XW2N8M5R6T7B1C2.jpg", 'fake bytes');
        // Storage::fake's underlying local adapter sets mtime to "now" —
        // simulate an old file by traveling time forward past the grace window.
        Carbon::setTestNow(now()->addHours(config('media.orphan_grace_hours') + 1));

        $summary = $this->service()->reconcileOrphanFiles();

        Carbon::setTestNow();
        $this->assertSame(1, $summary['removed']);
        $this->assertFalse($disk->exists("content/{$month}/01J9ZK3V7Q4XW2N8M5R6T7B1C2.jpg"));
    }

    public function test_recent_orphan_file_within_grace_is_not_removed(): void
    {
        $disk = Storage::disk(config('media.disk'));
        $month = now()->format('Y/m');
        $disk->put("content/{$month}/01J9ZK3V7Q4XW2N8M5R6T7B1C3.jpg", 'fake bytes');

        $summary = $this->service()->reconcileOrphanFiles();

        $this->assertSame(0, $summary['removed']);
        $this->assertTrue($disk->exists("content/{$month}/01J9ZK3V7Q4XW2N8M5R6T7B1C3.jpg"));
    }

    public function test_a_file_matching_a_real_asset_row_is_never_touched(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $asset = app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $uploader);
        Carbon::setTestNow(now()->addHours(config('media.orphan_grace_hours') + 1));

        $summary = $this->service()->reconcileOrphanFiles();

        Carbon::setTestNow();
        $this->assertSame(0, $summary['removed']);
        $month = now()->format('Y/m');
        $this->assertTrue(Storage::disk(config('media.disk'))->exists("content/{$month}/{$asset->stored_filename}"));
    }

    public function test_a_badly_named_file_is_refused_not_deleted(): void
    {
        $disk = Storage::disk(config('media.disk'));
        $month = now()->format('Y/m');
        $disk->put("content/{$month}/not-a-valid-name.txt", 'x');
        Carbon::setTestNow(now()->addHours(config('media.orphan_grace_hours') + 1));

        $summary = $this->service()->reconcileOrphanFiles();

        Carbon::setTestNow();
        $this->assertSame(1, $summary['refused']);
        $this->assertSame(0, $summary['removed']);
        $this->assertTrue($disk->exists("content/{$month}/not-a-valid-name.txt"));
    }

    // --- Case B: unreferenced archived assets ---

    public function test_archived_unreferenced_asset_past_grace_is_purged(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $mediaService = app(MediaService::class);
        $asset = $mediaService->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $uploader);
        $mediaService->archive($asset, $uploader);
        $asset->fresh()->forceFill(['archived_at' => now()->subDays(config('media.purge_grace_days') + 1)])->save();

        $summary = $this->service()->purgeUnreferencedAssets($this->cleanupPrincipal());

        $this->assertSame(1, $summary['purged']);
        $this->assertSame('PURGED', $asset->fresh()->status);

        $event = AuditRecord::where('event_type', 'content.media.purged')->latest('id')->firstOrFail();
        $this->assertSame($asset->ulid, $event->metadata['asset_ulid']);
        $this->assertSame(1, $event->metadata['active_references_verified_absent']);
        $this->assertSame($uploader->id, $event->metadata['previously_archived_by_principal_id']);
        $this->assertSame('system', $event->actor_principal_kind);
    }

    public function test_archived_asset_within_grace_is_not_purged(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $mediaService = app(MediaService::class);
        $asset = $mediaService->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $uploader);
        $mediaService->archive($asset, $uploader); // archived_at = now(), grace not elapsed

        $summary = $this->service()->purgeUnreferencedAssets($this->cleanupPrincipal());

        $this->assertSame(0, $summary['purged']);
        $this->assertSame('ARCHIVED', $asset->fresh()->status);
    }

    public function test_active_asset_is_never_a_purge_candidate(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $uploader);

        $summary = $this->service()->purgeUnreferencedAssets($this->cleanupPrincipal());

        $this->assertSame(0, $summary['purged']);
    }

    public function test_referenced_asset_is_refused_even_past_grace(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $mediaService = app(MediaService::class);
        $asset = $mediaService->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $uploader);

        // Embed the asset WHILE it is still ACTIVE (attaching to an already-
        // archived asset would itself be rejected, media_asset_not_attachable)
        // — this is what the attachment protocol (RevisionService) writes in
        // practice; the reference row is what the recheck below must honor.
        $page = CmsPage::create([
            'title' => 'x', 'status' => 'DRAFT',
            'created_by_principal_id' => $uploader->id, 'updated_by_principal_id' => $uploader->id,
        ]);
        app(RevisionService::class)->createDraft($page, [
            'title' => 'x',
            'body_html' => "<p><img data-media=\"{$asset->ulid}\" alt=\"x\"></p>",
        ], $uploader);

        $mediaService->archive($asset->fresh(), $uploader);
        $asset->fresh()->forceFill(['archived_at' => now()->subDays(config('media.purge_grace_days') + 1)])->save();

        $summary = $this->service()->purgeUnreferencedAssets($this->cleanupPrincipal());

        // The candidate QUERY itself already excludes assets with an ACTIVE
        // reference — it never becomes a candidate, so 'refused' (reserved
        // for the recheck-under-lock catching a RACE) stays 0 here too.
        $this->assertSame(0, $summary['purged']);
        $this->assertSame(0, $summary['refused']);
        $this->assertSame('ARCHIVED', $asset->fresh()->status);
    }

    public function test_purge_is_idempotent_on_a_second_run(): void
    {
        $uploader = $this->makeUnauthorizedActor();
        $mediaService = app(MediaService::class);
        $asset = $mediaService->upload(UploadedFile::fake()->image('a.jpg', 5, 5), $uploader);
        $mediaService->archive($asset, $uploader);
        $asset->fresh()->forceFill(['archived_at' => now()->subDays(config('media.purge_grace_days') + 1)])->save();
        $service = $this->service();
        $service->purgeUnreferencedAssets($this->cleanupPrincipal());
        $countAfterFirst = AuditRecord::where('event_type', 'content.media.purged')->count();

        $summary = $service->purgeUnreferencedAssets($this->cleanupPrincipal());

        $this->assertSame(0, $summary['purged'], 'already PURGED, no longer a candidate');
        $this->assertSame($countAfterFirst, AuditRecord::where('event_type', 'content.media.purged')->count());
    }

    /**
     * Exercises the actual Artisan command, not just the service — the
     * class this session's own history shows string-interpolation syntax
     * errors can hide from until a command is actually invoked.
     */
    public function test_the_console_command_runs_both_cases_successfully(): void
    {
        $this->artisan('content:cleanup-media')->assertSuccessful();
    }

    public function test_the_console_command_fails_cleanly_when_the_cleanup_principal_is_not_seeded(): void
    {
        SystemPrincipal::where('code', 'content.media_cleanup')->update(['code' => 'content.media_cleanup.renamed']);

        $this->artisan('content:cleanup-media')->assertFailed();
    }
}
