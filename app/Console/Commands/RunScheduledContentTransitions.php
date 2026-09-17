<?php

namespace App\Console\Commands;

use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Content\PublicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * IMP-005 — Q32 scheduled execution (docs/implementation/IMP-005-cms.md
 * section 10 "Execution"). Invoked by the Laravel Scheduler every minute
 * through Cron (shared-hosting compatible — no Redis/Supervisor/PM2/
 * WebSocket/separate worker). Captures ONE UTC cutoff for the whole run,
 * selects identities whose publish_at OR unpublish_at is <= that cutoff
 * (a pre-lock HINT — section 10: "select due timestamps <= cutoff, not
 * equality, so missed cron runs are caught"), and delegates every actual
 * decision to PublicationService::executeScheduledTransition(), which
 * re-derives due-ness under its own lock. This command never mutates
 * content state itself and never emits an audit event itself — it is a
 * thin per-identity driver.
 */
class RunScheduledContentTransitions extends Command
{
    protected $signature = 'content:run-scheduled-transitions';

    protected $description = 'Execute due CMS scheduled publish/unpublish transitions (IMP-005 Q32).';

    public function handle(PublicationService $publicationService): int
    {
        $systemActor = $this->resolveSchedulerPrincipal();

        if ($systemActor === null) {
            $this->error('content.scheduler System Principal is not seeded — run CmsSystemPrincipalSeeder first.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now('UTC');

        $dueCount = 0;

        foreach ($this->dueOwners(CmsPage::class, $cutoff) as $page) {
            $dueCount++;
            $this->runOne($publicationService, $page, $cutoff, $systemActor);
        }

        foreach ($this->dueOwners(CmsArticle::class, $cutoff) as $article) {
            $dueCount++;
            $this->runOne($publicationService, $article, $cutoff, $systemActor);
        }

        $this->info("Processed {$dueCount} due CMS schedule(s) at cutoff {$cutoff->format('Y-m-d\TH:i:s\Z')}.");

        return self::SUCCESS;
    }

    /**
     * @param  class-string<CmsPage>|class-string<CmsArticle>  $model
     * @return Collection<int, CmsPage|CmsArticle>
     */
    private function dueOwners(string $model, Carbon $cutoff)
    {
        return $model::query()
            ->where(function ($query) use ($cutoff) {
                $query->where('publish_at', '<=', $cutoff)->orWhere('unpublish_at', '<=', $cutoff);
            })
            ->get();
    }

    private function runOne(PublicationService $publicationService, CmsPage|CmsArticle $owner, Carbon $cutoff, Principal $systemActor): void
    {
        try {
            $publicationService->executeScheduledTransition($owner, $cutoff, $systemActor);
        } catch (\Throwable $e) {
            // Section 10: "transient failures leave due work pending" — a
            // single identity's failure must never abort the whole run (the
            // next minute's run will retry it), and never crashes the batch.
            logger()->error('CMS scheduled transition failed for one identity; will retry next run.', [
                'owner_class' => $owner::class,
                'owner_id' => $owner->id,
                'exception' => $e->getMessage(),
            ]);
            $this->error('Failed to process '.$owner::class." #{$owner->id}: ".$e->getMessage());
        }
    }

    private function resolveSchedulerPrincipal(): ?Principal
    {
        $systemPrincipal = SystemPrincipal::where('code', 'content.scheduler')->first();

        if ($systemPrincipal === null) {
            return null;
        }

        return Principal::where('system_principal_id', $systemPrincipal->id)->first();
    }
}
