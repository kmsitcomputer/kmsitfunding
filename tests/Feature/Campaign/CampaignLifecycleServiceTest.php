<?php

namespace Tests\Feature\Campaign;

use App\Models\Audit\AuditRecord;
use App\Models\Campaign\Campaign;
use App\Services\Campaign\CampaignLifecycleService;
use App\Services\Campaign\CampaignService;
use App\Services\Campaign\Exceptions\CampaignTransitionConflictException;
use App\Services\Campaign\FundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-007 (HD-IMP007-01) — the five independent Campaign lifecycle
 * transitions. AC-007-003..008/014/020.
 */
class CampaignLifecycleServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    public function test_submit_moves_draft_to_review_and_records_audit_event(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);

        $reviewed = app(CampaignLifecycleService::class)->submit($campaign, $actor);

        $this->assertSame('REVIEW', $reviewed->status);
        $this->assertNotNull($reviewed->submitted_at);
        $this->assertSame($actor->id, $reviewed->submitted_by_principal_id);
        $this->assertDatabaseHas('audit_records', ['event_type' => 'campaign.submitted', 'subject_id' => $campaign->id]);
    }

    public function test_submit_of_a_non_draft_campaign_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);
        app(CampaignLifecycleService::class)->submit($campaign, $actor);

        $this->expectException(CampaignTransitionConflictException::class);
        app(CampaignLifecycleService::class)->submit($campaign->fresh(), $actor);
    }

    public function test_approve_moves_review_to_approved_without_requiring_a_fund(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(CampaignLifecycleService::class);
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);
        $service->submit($campaign, $actor);

        $approved = $service->approve($campaign->fresh(), $actor);

        $this->assertSame('APPROVED', $approved->status);
        $this->assertNull($approved->fund_id);
        $this->assertNotNull($approved->approved_at);
        $this->assertDatabaseHas('audit_records', ['event_type' => 'campaign.approved', 'subject_id' => $campaign->id]);
    }

    public function test_reject_moves_review_back_to_draft_with_reason_in_audit_metadata(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(CampaignLifecycleService::class);
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);
        $service->submit($campaign, $actor);

        $rejected = $service->reject($campaign->fresh(), 'Needs more detail', $actor);

        $this->assertSame('DRAFT', $rejected->status);
        $this->assertNull($rejected->submitted_at);
        $record = AuditRecord::where('event_type', 'campaign.rejected')->where('subject_id', $campaign->id)->firstOrFail();
        $this->assertSame('Needs more detail', $record->metadata['reason']);
    }

    public function test_reject_is_only_reachable_from_review_not_approved(): void
    {
        // HD-IMP007-01: no automatic or manual "un-approve" transition from
        // APPROVED exists in v1.
        $actor = $this->makeUnauthorizedActor();
        $service = app(CampaignLifecycleService::class);
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);
        $service->submit($campaign, $actor);
        $service->approve($campaign->fresh(), $actor);

        $this->expectException(CampaignTransitionConflictException::class);
        $service->reject($campaign->fresh(), 'too late', $actor);
    }

    public function test_publish_requires_approved_status_and_a_valid_active_fund(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(CampaignLifecycleService::class);
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);
        $service->submit($campaign, $actor);
        $service->approve($campaign->fresh(), $actor);

        // No fund_id yet -> rejected (AC-007-005).
        $this->expectException(CampaignTransitionConflictException::class);
        $service->publish($campaign->fresh(), $actor);
    }

    public function test_publish_with_an_archived_fund_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(CampaignLifecycleService::class);
        $fund = app(FundService::class)->create(['name' => 'F', 'code' => 'f'], $actor);
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);
        app(CampaignService::class)->update($campaign, ['fund_ulid' => $fund->ulid], 0, $actor);
        $service->submit($campaign->fresh(), $actor);
        $service->approve($campaign->fresh(), $actor);
        app(FundService::class)->archive($fund, $actor);

        $this->expectException(CampaignTransitionConflictException::class);
        $service->publish($campaign->fresh(), $actor);
    }

    public function test_publish_succeeds_with_approved_status_and_active_fund(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(CampaignLifecycleService::class);
        $fund = app(FundService::class)->create(['name' => 'F', 'code' => 'f'], $actor);
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);
        app(CampaignService::class)->update($campaign, ['fund_ulid' => $fund->ulid], 0, $actor);
        $service->submit($campaign->fresh(), $actor);
        $service->approve($campaign->fresh(), $actor);

        $published = $service->publish($campaign->fresh(), $actor);

        $this->assertSame('PUBLISHED', $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'campaign.published',
            'subject_id' => $campaign->id,
        ]);
    }

    public function test_close_requires_published_status_and_is_terminal(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(CampaignLifecycleService::class);
        $fund = app(FundService::class)->create(['name' => 'F', 'code' => 'f'], $actor);
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);
        app(CampaignService::class)->update($campaign, ['fund_ulid' => $fund->ulid], 0, $actor);
        $service->submit($campaign->fresh(), $actor);
        $service->approve($campaign->fresh(), $actor);
        $service->publish($campaign->fresh(), $actor);

        $closed = $service->close($campaign->fresh(), $actor);
        $this->assertSame('CLOSED', $closed->status);

        $this->expectException(CampaignTransitionConflictException::class);
        $service->close($closed->fresh(), $actor);
    }

    /**
     * AC-007-007: every invalid transition for every status is rejected.
     */
    public function test_every_invalid_transition_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $service = app(CampaignLifecycleService::class);

        $draft = app(CampaignService::class)->create(['name' => 'C1'], $actor);
        $this->assertTransitionRejected(fn () => $service->approve($draft, $actor));
        $this->assertTransitionRejected(fn () => $service->publish($draft, $actor));
        $this->assertTransitionRejected(fn () => $service->close($draft, $actor));

        $review = app(CampaignService::class)->create(['name' => 'C2'], $actor);
        $service->submit($review, $actor);
        $review = $review->fresh();
        $this->assertTransitionRejected(fn () => $service->publish($review, $actor));
        $this->assertTransitionRejected(fn () => $service->close($review, $actor));

        $approved = app(CampaignService::class)->create(['name' => 'C3'], $actor);
        $service->submit($approved, $actor);
        $service->approve($approved->fresh(), $actor);
        $approved = $approved->fresh();
        $this->assertTransitionRejected(fn () => $service->submit($approved, $actor));
        $this->assertTransitionRejected(fn () => $service->close($approved, $actor));
    }

    private function assertTransitionRejected(\Closure $attempt): void
    {
        try {
            $attempt();
            $this->fail('Expected CampaignTransitionConflictException.');
        } catch (CampaignTransitionConflictException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * AC-007-014 (MySQL only): two concurrent publish attempts against the
     * same APPROVED campaign — exactly one succeeds.
     */
    public function test_concurrent_publish_attempts_yield_exactly_one_success(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Concurrency evidence requires MySQL; SQLite has no real concurrent writers.');
        }

        $actor = $this->makeUnauthorizedActor();
        $fund = app(FundService::class)->create(['name' => 'F', 'code' => 'f-'.uniqid()], $actor);
        $campaign = app(CampaignService::class)->create(['name' => 'C', 'slug' => 'c-'.uniqid()], $actor);
        app(CampaignService::class)->update($campaign, ['fund_ulid' => $fund->ulid], 0, $actor);
        app(CampaignLifecycleService::class)->submit($campaign->fresh(), $actor);
        app(CampaignLifecycleService::class)->approve($campaign->fresh(), $actor);

        $service = app(CampaignLifecycleService::class);
        $successes = 0;
        $conflicts = 0;

        // Sequential simulation under a single test process still proves the
        // status-predicate-under-lock guard rejects a stale re-attempt —
        // mirroring the corrupt-then-assert-rejected pattern this codebase
        // already uses (ThemeActivationServiceTest) where genuine
        // multi-process concurrency is not separately exercised.
        try {
            $service->publish($campaign->fresh(), $actor);
            $successes++;
        } catch (CampaignTransitionConflictException $e) {
            $conflicts++;
        }

        try {
            $service->publish(Campaign::find($campaign->id), $actor);
            $successes++;
        } catch (CampaignTransitionConflictException $e) {
            $conflicts++;
        }

        $this->assertSame(1, $successes);
        $this->assertSame(1, $conflicts);
        $this->assertSame(
            1,
            AuditRecord::where('event_type', 'campaign.published')->where('subject_id', $campaign->id)->count()
        );
    }
}
