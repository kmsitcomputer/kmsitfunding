<?php

namespace Tests\Feature\Cms;

use App\Models\Audit\AuditRecord;
use App\Models\Cms\CmsPage;
use App\Models\Rbac\Principal;
use App\Models\Rbac\SystemPrincipal;
use App\Services\Content\Exceptions\PublicationValidationException;
use App\Services\Content\PublicationService;
use App\Services\Content\RevisionService;
use Database\Seeders\CmsSystemPrincipalSeeder;
use Database\Seeders\RbacPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-005 slice 18 (scheduler) coverage — docs/implementation/IMP-005-cms.md
 * section 10 Q32 "Scheduled execution contract" + section 12 "Expired-window
 * semantics".
 */
class SchedulerTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacPermissionSeeder::class);
        $this->seed(CmsSystemPrincipalSeeder::class);
    }

    private function schedulerPrincipal(): Principal
    {
        $systemPrincipal = SystemPrincipal::where('code', 'content.scheduler')->firstOrFail();

        return Principal::where('system_principal_id', $systemPrincipal->id)->firstOrFail();
    }

    /**
     * A Human actor who ALSO holds content.publish at ORGANIZATION scope
     * (RbacTestActors::makeAuthorizedActor grants at GLOBAL_PLATFORM, which
     * the canonical evaluator treats as always-covering — so this actor
     * passes the scheduler's own re-check of "source Human still eligible").
     */
    private function eligibleHuman(): Principal
    {
        return $this->makeAuthorizedActor();
    }

    private function draftPage(Principal $author): CmsPage
    {
        $page = CmsPage::create([
            'title' => 'x', 'status' => 'DRAFT',
            'created_by_principal_id' => $author->id, 'updated_by_principal_id' => $author->id,
        ]);
        app(RevisionService::class)->createDraft($page, ['title' => 'x', 'body_html' => '<p>x</p>'], $author);

        return $page->fresh();
    }

    public function test_schedule_configure_sets_columns_and_emits_configured(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();
        $publishAt = Carbon::now()->addHour();

        $configured = app(PublicationService::class)->scheduleConfigure($page, $human, $publishAt, null, $draft, expectedScheduleVersion: 0, rawTargetPath: '/scheduled');

        $this->assertEquals($publishAt->timestamp, $configured->publish_at->timestamp);
        $this->assertSame(1, $configured->schedule_version);
        $this->assertSame($draft->id, $configured->scheduled_revision_id);

        $event = AuditRecord::where('event_type', 'content.page.schedule_updated')->latest('id')->firstOrFail();
        $this->assertSame('CONFIGURED', $event->metadata['operation']);
        $this->assertArrayHasKey('publish_at', $event->metadata);
        $this->assertArrayNotHasKey('unpublish_at', $event->metadata);
    }

    public function test_schedule_configure_rejects_a_non_future_deadline(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();

        $this->expectException(PublicationValidationException::class);
        app(PublicationService::class)->scheduleConfigure($page, $human, Carbon::now()->subMinute(), null, $draft, 0);
    }

    public function test_schedule_configure_rejects_unpublish_at_before_publish_at(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();

        $this->expectException(PublicationValidationException::class);
        app(PublicationService::class)->scheduleConfigure(
            $page, $human, Carbon::now()->addHours(2), Carbon::now()->addHour(), $draft, 0
        );
    }

    public function test_schedule_configure_rejects_stale_expected_version(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();
        $service = app(PublicationService::class);
        $service->scheduleConfigure($page, $human, Carbon::now()->addHour(), null, $draft, 0, rawTargetPath: '/scheduled');

        $this->expectException(PublicationValidationException::class);
        $service->scheduleConfigure($page->fresh(), $human, Carbon::now()->addHours(2), null, $draft, expectedScheduleVersion: 0);
    }

    public function test_cancel_clears_all_schedule_columns_and_emits_cancelled(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();
        $service = app(PublicationService::class);
        $scheduled = $service->scheduleConfigure($page, $human, Carbon::now()->addHour(), null, $draft, 0, rawTargetPath: '/scheduled');

        $cancelled = $service->scheduleConfigure($scheduled, $human, null, null, null, expectedScheduleVersion: 1);

        $this->assertNull($cancelled->publish_at);
        $this->assertNull($cancelled->scheduled_revision_id);
        $this->assertNull($cancelled->scheduled_by_principal_id);

        $event = AuditRecord::where('event_type', 'content.page.schedule_updated')->latest('id')->firstOrFail();
        $this->assertSame('CANCELLED', $event->metadata['operation']);
        $this->assertArrayNotHasKey('scheduled_revision_id', $event->metadata);
    }

    public function test_valid_due_publish_executes_and_emits_published_with_schedule_keys(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();
        $service = app(PublicationService::class);
        $scheduled = $service->scheduleConfigure($page, $human, Carbon::now()->addMinute(), null, $draft, 0, rawTargetPath: '/scheduled');

        $cutoff = Carbon::now()->addMinutes(2);
        $service->executeScheduledTransition($scheduled, $cutoff, $this->schedulerPrincipal());

        $page->refresh();
        $this->assertSame('PUBLISHED', $page->status);
        $this->assertNull($page->publish_at, 'the due deadline was consumed');

        $event = AuditRecord::where('event_type', 'content.page.published')->latest('id')->firstOrFail();
        $this->assertSame('system', $event->actor_principal_kind);
        $this->assertArrayHasKey('schedule_version', $event->metadata);
        $this->assertArrayHasKey('scheduled_by_principal_id', $event->metadata);
        $this->assertSame('content.scheduler', $event->metadata['system_operation']);
        $this->assertSame(0, AuditRecord::where('event_type', 'content.page.schedule_expired')->count(), 'a real transition never ALSO emits schedule_expired');
    }

    public function test_manual_publish_never_carries_schedule_keys(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();

        app(PublicationService::class)->publish($page, $draft, $human, '/manual');

        $event = AuditRecord::where('event_type', 'content.page.published')->latest('id')->firstOrFail();
        $this->assertSame('human', $event->actor_principal_kind);
        $this->assertArrayNotHasKey('schedule_version', $event->metadata);
        $this->assertArrayNotHasKey('system_operation', $event->metadata);
    }

    public function test_not_yet_due_is_a_no_op(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();
        $service = app(PublicationService::class);
        $scheduled = $service->scheduleConfigure($page, $human, Carbon::now()->addHours(2), null, $draft, 0, rawTargetPath: '/scheduled');

        $service->executeScheduledTransition($scheduled, Carbon::now(), $this->schedulerPrincipal());

        $this->assertSame('DRAFT', $page->fresh()->status);
        $this->assertSame(0, AuditRecord::whereIn('event_type', [
            'content.page.published', 'content.page.unpublished', 'content.page.schedule_expired',
        ])->count());
    }

    public function test_already_consumed_reexecution_is_idempotent_no_op(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();
        $service = app(PublicationService::class);
        $scheduled = $service->scheduleConfigure($page, $human, Carbon::now()->addMinute(), null, $draft, 0, rawTargetPath: '/scheduled');
        $cutoff = Carbon::now()->addMinutes(2);
        $service->executeScheduledTransition($scheduled, $cutoff, $this->schedulerPrincipal());
        $countAfterFirst = AuditRecord::where('event_type', 'content.page.published')->count();

        // Duplicate execution (e.g. overlapping cron) against the SAME
        // stale in-memory instance re-run.
        $service->executeScheduledTransition($scheduled, $cutoff, $this->schedulerPrincipal());

        $this->assertSame($countAfterFirst, AuditRecord::where('event_type', 'content.page.published')->count());
    }

    public function test_cancelled_schedule_never_executes(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();
        $service = app(PublicationService::class);
        $scheduled = $service->scheduleConfigure($page, $human, Carbon::now()->addMinute(), null, $draft, 0, rawTargetPath: '/scheduled');
        $cancelled = $service->scheduleConfigure($scheduled, $human, null, null, null, 1);

        $service->executeScheduledTransition($cancelled, Carbon::now()->addMinutes(5), $this->schedulerPrincipal());

        $this->assertSame('DRAFT', $page->fresh()->status);
    }

    public function test_both_deadlines_missed_consumes_without_publishing_and_emits_schedule_expired(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();
        $service = app(PublicationService::class);

        // Configure publish, then reconfigure to add an unpublish deadline
        // that (by the time we run the cutoff) has ALSO passed.
        $scheduled = $service->scheduleConfigure(
            $page, $human, Carbon::now()->addMinute(), Carbon::now()->addMinutes(2), $draft, 0, rawTargetPath: '/scheduled'
        );

        $cutoff = Carbon::now()->addMinutes(5);
        $service->executeScheduledTransition($scheduled, $cutoff, $this->schedulerPrincipal());

        $page->refresh();
        $this->assertSame('DRAFT', $page->status, 'never briefly published');
        $this->assertNull($page->publish_at);
        $this->assertNull($page->unpublish_at);

        $event = AuditRecord::where('event_type', 'content.page.schedule_expired')->latest('id')->firstOrFail();
        $this->assertSame('PUBLISH_SUPERSEDED_BY_UNPUBLISH', $event->metadata['outcome']);
    }

    public function test_unpublish_due_with_no_retirement_target_emits_no_retirement_target(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();
        $service = app(PublicationService::class);
        // Publish manually first (so there IS a published_revision_id to bind to),
        // then schedule a standalone unpublish, then retire it manually before
        // the scheduler ever runs — leaving nothing to retire.
        $service->publish($page, $draft, $human, '/x');
        $scheduled = $service->scheduleConfigure($page->fresh(), $human, null, Carbon::now()->addMinute(), null, 0);
        $service->unpublish($scheduled->fresh(), $human);

        // The manual unpublish's cancelPendingScheduleSilently() should have
        // already cleared unpublish_at — re-configure to simulate a race
        // where the column is still set (defensive test of the branch itself).
        $scheduled->fresh()->forceFill([
            'unpublish_at' => Carbon::now()->addMinute(),
            'scheduled_revision_id' => $draft->id,
            'scheduled_by_principal_id' => $human->id,
        ])->save();

        $service->executeScheduledTransition($scheduled->fresh(), Carbon::now()->addMinutes(2), $this->schedulerPrincipal());

        $event = AuditRecord::where('event_type', 'content.page.schedule_expired')->latest('id')->firstOrFail();
        $this->assertSame('NO_RETIREMENT_TARGET', $event->metadata['outcome']);
    }

    public function test_target_invalid_because_revision_superseded_emits_target_invalid(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft1 = $page->currentDraft()->first();
        $service = app(PublicationService::class);
        $scheduled = $service->scheduleConfigure($page, $human, Carbon::now()->addMinute(), null, $draft1, 0, rawTargetPath: '/scheduled');

        // The bound draft gets superseded by a manual publish of a DIFFERENT
        // revision before the scheduled one ever runs.
        $draft1->forceFill(['state' => 'SUPERSEDED', 'superseded_at' => now()])->save();

        $service->executeScheduledTransition($scheduled->fresh(), Carbon::now()->addMinutes(2), $this->schedulerPrincipal());

        $page->refresh();
        $this->assertSame('DRAFT', $page->status);
        $event = AuditRecord::where('event_type', 'content.page.schedule_expired')->latest('id')->firstOrFail();
        $this->assertSame('TARGET_INVALID', $event->metadata['outcome']);
    }

    public function test_human_publish_vs_scheduler_manual_wins_and_cancels_pending_intent(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();
        $service = app(PublicationService::class);
        $scheduled = $service->scheduleConfigure($page, $human, Carbon::now()->addMinute(), null, $draft, 0, rawTargetPath: '/scheduled');

        // Human manually publishes before the scheduler ever runs.
        $service->publish($scheduled, $draft, $human, '/manual-first');

        // The scheduler's later run against its own (now stale) view finds
        // nothing due once it re-reads under the lock.
        $service->executeScheduledTransition($page->fresh(), Carbon::now()->addMinutes(5), $this->schedulerPrincipal());

        $this->assertSame(1, AuditRecord::where('event_type', 'content.page.published')->count(), 'exactly one publish, the manual one');
        $this->assertSame(0, AuditRecord::where('event_type', 'content.page.schedule_expired')->count());
    }

    public function test_source_human_no_longer_eligible_blocks_execution_without_consuming(): void
    {
        $human = $this->makeUnauthorizedActor(); // holds NO permissions
        $page = $this->draftPage($this->eligibleHuman());
        $draft = $page->currentDraft()->first();

        // Directly force a schedule bound to an ineligible Human (bypassing
        // scheduleConfigure()'s own authorization-adjacent checks, to
        // exercise the executor's own re-verification in isolation).
        $page->forceFill([
            'publish_at' => Carbon::now()->addMinute(),
            'scheduled_revision_id' => $draft->id,
            'scheduled_by_principal_id' => $human->id,
            'scheduled_at' => now(),
            'schedule_version' => 1,
        ])->save();

        app(PublicationService::class)->executeScheduledTransition($page->fresh(), Carbon::now()->addMinutes(2), $this->schedulerPrincipal());

        $page->refresh();
        $this->assertSame('DRAFT', $page->status, 'blocked, not forced');
        $this->assertNotNull($page->publish_at, 'left pending for operator correction, not consumed');
    }

    /**
     * Exercises the actual Artisan command (not just the underlying
     * service) — the only test in this file that would have caught the
     * string-interpolation syntax error initially shipped in
     * RunScheduledContentTransitions.php, which no service-level test
     * could reach.
     */
    public function test_the_console_command_runs_and_executes_a_due_publish(): void
    {
        $human = $this->eligibleHuman();
        $page = $this->draftPage($human);
        $draft = $page->currentDraft()->first();
        app(PublicationService::class)->scheduleConfigure(
            $page, $human, Carbon::now()->addMinute(), null, $draft, 0, rawTargetPath: '/cli-scheduled'
        );

        Carbon::setTestNow(Carbon::now()->addMinutes(2));

        try {
            $this->artisan('content:run-scheduled-transitions')->assertSuccessful();
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame('PUBLISHED', $page->fresh()->status);
    }

    public function test_the_console_command_fails_cleanly_when_the_scheduler_principal_is_not_seeded(): void
    {
        // Rename rather than delete — a real delete would violate the FK
        // from the already-linked principals row (System Principals are
        // never hard-deleted, IMP-003). Renaming the code is sufficient to
        // simulate "not found by that code" for this command's own lookup.
        SystemPrincipal::where('code', 'content.scheduler')->update(['code' => 'content.scheduler.renamed']);

        $this->artisan('content:run-scheduled-transitions')->assertFailed();
    }
}
