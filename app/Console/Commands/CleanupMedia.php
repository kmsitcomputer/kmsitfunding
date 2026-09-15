<?php

namespace App\Console\Commands;

use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Content\MediaCleanupService;
use Illuminate\Console\Command;

/**
 * IMP-005 — section 19 "cleanup actor / authority": explicit system
 * operation identifier `content.media_cleanup`, executed via this console
 * command. Runs both cleanup cases (orphan files, unreferenced archived
 * assets) under the content.media_cleanup System Principal — bounded to
 * content.archive only, never content.publish/content.update, never a
 * Human role impersonated.
 */
class CleanupMedia extends Command
{
    protected $signature = 'content:cleanup-media';

    protected $description = 'Reconcile orphan media files and purge unreferenced archived media assets (IMP-005 section 19).';

    public function handle(MediaCleanupService $cleanupService): int
    {
        $systemActor = $this->resolveCleanupPrincipal();

        if ($systemActor === null) {
            $this->error('content.media_cleanup System Principal is not seeded — run CmsSystemPrincipalSeeder first.');

            return self::FAILURE;
        }

        $orphanSummary = $cleanupService->reconcileOrphanFiles();
        $this->info(sprintf(
            'Orphan files — scanned: %d, removed: %d, failed: %d, refused: %d',
            $orphanSummary['scanned'], $orphanSummary['removed'], $orphanSummary['failed'], $orphanSummary['refused']
        ));

        $purgeSummary = $cleanupService->purgeUnreferencedAssets($systemActor);
        $this->info(sprintf(
            'Media purge — purged: %d, refused: %d, failed: %d',
            $purgeSummary['purged'], $purgeSummary['refused'], $purgeSummary['failed']
        ));

        return self::SUCCESS;
    }

    private function resolveCleanupPrincipal(): ?Principal
    {
        $systemPrincipal = SystemPrincipal::where('code', 'content.media_cleanup')->first();

        if ($systemPrincipal === null) {
            return null;
        }

        return Principal::where('system_principal_id', $systemPrincipal->id)->first();
    }
}
