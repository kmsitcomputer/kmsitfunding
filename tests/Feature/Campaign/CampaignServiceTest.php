<?php

namespace Tests\Feature\Campaign;

use App\Models\Campaign\Program;
use App\Services\Campaign\CampaignService;
use App\Services\Campaign\Exceptions\CampaignValidationException;
use App\Services\Campaign\FundService;
use App\Services\Campaign\ProgramService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-007 — Campaign identity CRUD (plain fields only — lifecycle
 * transitions are CampaignLifecycleServiceTest's concern). AC-007-002,
 * BR-3/BR-4/BR-9.
 */
class CampaignServiceTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    public function test_create_persists_a_draft_campaign_with_no_fund_and_records_audit_event(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = app(CampaignService::class)->create(['name' => 'Help Now'], $actor);

        $this->assertSame('DRAFT', $campaign->status);
        $this->assertNull($campaign->fund_id);
        $this->assertDatabaseHas('audit_records', ['event_type' => 'campaign.created', 'subject_id' => $campaign->id]);
    }

    public function test_create_optionally_attaches_an_existing_program(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $program = app(ProgramService::class)->create(['name' => 'Program A'], $actor);

        $campaign = app(CampaignService::class)->create(['name' => 'C', 'program_ulid' => $program->ulid], $actor);

        $this->assertSame($program->id, $campaign->program_id);
    }

    public function test_create_rejects_a_target_amount_in_an_unregistered_currency(): void
    {
        $actor = $this->makeUnauthorizedActor();

        $this->expectException(CampaignValidationException::class);
        app(CampaignService::class)->create([
            'name' => 'C', 'target_amount_minor' => 1000, 'currency' => 'XXX',
        ], $actor);
    }

    public function test_update_requires_matching_edit_version(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);

        $this->expectException(CampaignValidationException::class);
        app(CampaignService::class)->update($campaign, ['name' => 'D'], 999, $actor);
    }

    public function test_update_assigning_a_fund_records_fund_assigned_audit_event(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);
        $fund = app(FundService::class)->create(['name' => 'F', 'code' => 'f'], $actor);

        $updated = app(CampaignService::class)->update($campaign, ['fund_ulid' => $fund->ulid], 0, $actor);

        $this->assertSame($fund->id, $updated->fund_id);
        $this->assertDatabaseHas('audit_records', [
            'event_type' => 'campaign.fund_assigned',
            'subject_id' => $campaign->id,
        ]);
    }

    public function test_update_of_a_closed_campaign_is_rejected(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);
        $campaign->forceFill(['status' => 'CLOSED'])->save();

        $this->expectException(CampaignValidationException::class);
        app(CampaignService::class)->update($campaign, ['name' => 'D'], 0, $actor);
    }

    public function test_deleting_a_program_still_referenced_by_a_campaign_is_rejected_at_database_level(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $program = app(ProgramService::class)->create(['name' => 'P'], $actor);
        app(CampaignService::class)->create(['name' => 'C', 'program_ulid' => $program->ulid], $actor);

        $this->expectException(QueryException::class);
        Program::query()->whereKey($program->id)->delete();
    }
}
