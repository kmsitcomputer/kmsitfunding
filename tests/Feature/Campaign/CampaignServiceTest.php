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

    public function test_create_rejects_an_unregistered_currency_even_without_a_target_amount(): void
    {
        // Completion-phase remediation: the registry check previously ran only
        // when an amount accompanied the currency, so an unregistered code
        // could be persisted and would later break Money::ofMinorUnits()
        // (section 13 / AC-007-021).
        $actor = $this->makeUnauthorizedActor();

        $this->expectException(CampaignValidationException::class);
        app(CampaignService::class)->create(['name' => 'C', 'currency' => 'XXX'], $actor);
    }

    public function test_update_rejects_an_unregistered_currency_without_a_target_amount(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);

        $this->expectException(CampaignValidationException::class);
        app(CampaignService::class)->update($campaign, ['currency' => 'XXX'], 0, $actor);
    }

    public function test_create_rejects_an_ends_at_before_starts_at(): void
    {
        // section 13: "if both present, ends_at must be >= starts_at".
        $actor = $this->makeUnauthorizedActor();

        $this->expectException(CampaignValidationException::class);
        app(CampaignService::class)->create([
            'name' => 'C', 'starts_at' => '2026-06-01', 'ends_at' => '2026-05-01',
        ], $actor);
    }

    public function test_update_rejects_an_ends_at_before_the_persisted_starts_at(): void
    {
        // Completion-phase remediation: a partial update supplying only
        // ends_at is a case the request-level `after_or_equal` rule alone
        // cannot cover.
        $actor = $this->makeUnauthorizedActor();
        $campaign = app(CampaignService::class)->create(['name' => 'C', 'starts_at' => '2026-06-01'], $actor);

        $this->expectException(CampaignValidationException::class);
        app(CampaignService::class)->update($campaign, ['ends_at' => '2026-05-01'], 0, $actor);
    }

    public function test_update_accepts_a_valid_period(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = app(CampaignService::class)->create(['name' => 'C', 'starts_at' => '2026-06-01'], $actor);

        $updated = app(CampaignService::class)->update($campaign, ['ends_at' => '2026-06-30'], 0, $actor);

        $this->assertSame('2026-06-30', $updated->ends_at->toDateString());
    }

    public function test_update_rejects_assigning_an_archived_fund(): void
    {
        // BR-5: an ARCHIVED Fund cannot be SELECTED at assignment time. The
        // publish-time guard (BR-1) is a second, independent check — never the
        // only one — and the admin UI's ACTIVE-only selector is presentation,
        // not enforcement.
        $actor = $this->makeUnauthorizedActor();
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);
        $fund = app(FundService::class)->create(['name' => 'F', 'code' => 'f-'.uniqid()], $actor);
        app(FundService::class)->archive($fund, $actor);

        $this->expectException(CampaignValidationException::class);
        app(CampaignService::class)->update($campaign, ['fund_ulid' => $fund->ulid], 0, $actor);
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

    public function test_create_rejects_a_script_tag_in_description_html(): void
    {
        // Remediation: description_html was previously persisted
        // unsanitized (real stored-XSS gap on public pages).
        $actor = $this->makeUnauthorizedActor();

        $this->expectException(CampaignValidationException::class);
        app(CampaignService::class)->create([
            'name' => 'C', 'description_html' => '<p>hi</p><script>alert(1)</script>',
        ], $actor);
    }

    public function test_update_sanitizes_description_html(): void
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = app(CampaignService::class)->create(['name' => 'C'], $actor);

        $updated = app(CampaignService::class)->update(
            $campaign,
            ['description_html' => '<p onclick="alert(1)">hello</p>'],
            0,
            $actor
        );

        $this->assertStringNotContainsString('onclick', $updated->description_html);
        $this->assertStringContainsString('hello', $updated->description_html);
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
