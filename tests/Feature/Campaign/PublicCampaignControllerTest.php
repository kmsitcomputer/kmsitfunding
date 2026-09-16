<?php

namespace Tests\Feature\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\Fund;
use App\Models\Campaign\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-007 — public Program/Campaign pages (docs/implementation/
 * IMP-007-campaign-program-fund.md sections 8b/14/20/21). AC-007-011/012/
 * 018/019.
 */
class PublicCampaignControllerTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeCampaign(array $overrides = []): Campaign
    {
        $actor = $this->makeUnauthorizedActor();
        $campaign = new Campaign;
        $campaign->forceFill(array_merge([
            'ulid' => (string) Str::ulid(),
            'name' => 'Public Campaign',
            'slug' => 'public-campaign-'.uniqid(),
            'status' => 'PUBLISHED',
            'edit_version' => 0,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ], $overrides));
        $campaign->save();

        return $campaign;
    }

    private function makeProgram(array $overrides = []): Program
    {
        $actor = $this->makeUnauthorizedActor();
        $program = new Program;
        $program->forceFill(array_merge([
            'ulid' => (string) Str::ulid(),
            'name' => 'Public Program',
            'slug' => 'public-program-'.uniqid(),
            'status' => 'PUBLISHED',
            'edit_version' => 0,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ], $overrides));
        $program->save();

        return $program;
    }

    public function test_a_published_campaign_is_publicly_reachable(): void
    {
        $campaign = $this->makeCampaign();

        $response = $this->get("/campaigns/{$campaign->slug}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Public/CampaignShow')->where('campaign.name', $campaign->name));
    }

    public function test_a_draft_campaign_is_not_publicly_reachable(): void
    {
        $campaign = $this->makeCampaign(['status' => 'DRAFT']);

        $this->get("/campaigns/{$campaign->slug}")->assertNotFound();
    }

    public function test_a_review_campaign_is_not_publicly_reachable(): void
    {
        $campaign = $this->makeCampaign(['status' => 'REVIEW']);

        $this->get("/campaigns/{$campaign->slug}")->assertNotFound();
    }

    public function test_an_approved_campaign_is_not_publicly_reachable(): void
    {
        $campaign = $this->makeCampaign(['status' => 'APPROVED']);

        $this->get("/campaigns/{$campaign->slug}")->assertNotFound();
    }

    public function test_a_closed_campaign_is_not_publicly_reachable(): void
    {
        $campaign = $this->makeCampaign(['status' => 'CLOSED']);

        $this->get("/campaigns/{$campaign->slug}")->assertNotFound();
    }

    /**
     * AC-007-011/018: a PUBLISHED campaign outside its period remains
     * reachable (200), with is_donation_eligible correctly false.
     */
    public function test_a_published_campaign_before_its_start_date_remains_reachable_but_not_eligible(): void
    {
        $campaign = $this->makeCampaign(['starts_at' => now()->addYear()]);

        $response = $this->get("/campaigns/{$campaign->slug}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Public/CampaignShow')->where('is_donation_eligible', false));
    }

    public function test_a_published_campaign_after_its_end_date_remains_reachable_but_not_eligible(): void
    {
        $campaign = $this->makeCampaign(['ends_at' => now()->subYear()]);

        $response = $this->get("/campaigns/{$campaign->slug}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Public/CampaignShow')->where('is_donation_eligible', false));
    }

    public function test_a_published_campaign_within_its_period_is_eligible(): void
    {
        $campaign = $this->makeCampaign(['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $response = $this->get("/campaigns/{$campaign->slug}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Public/CampaignShow')->where('is_donation_eligible', true));
    }

    public function test_campaign_index_shows_only_published_campaigns(): void
    {
        $published = $this->makeCampaign(['name' => 'Visible']);
        $this->makeCampaign(['name' => 'Hidden Draft', 'status' => 'DRAFT']);

        $response = $this->get('/campaigns');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Public/CampaignIndex')
            ->where('campaigns.data.0.name', $published->name)
        );
    }

    public function test_target_amount_is_formatted_via_the_money_value_object(): void
    {
        $campaign = $this->makeCampaign(['target_amount_minor' => 1000000, 'currency' => 'IDR']);

        $response = $this->get("/campaigns/{$campaign->slug}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('formatted_target_amount', 'IDR 10.000,00'));
    }

    public function test_internal_bigint_id_never_appears_in_the_public_response_payload(): void
    {
        $campaign = $this->makeCampaign();

        $response = $this->get("/campaigns/{$campaign->slug}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('campaign.ulid', $campaign->ulid)
            ->missing('campaign.id')
        );
    }

    public function test_a_published_program_is_publicly_reachable(): void
    {
        $program = $this->makeProgram();

        $response = $this->get("/programs/{$program->slug}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Public/ProgramShow')->where('program.name', $program->name));
    }

    public function test_a_draft_program_is_not_publicly_reachable(): void
    {
        $program = $this->makeProgram(['status' => 'DRAFT']);

        $this->get("/programs/{$program->slug}")->assertNotFound();
    }

    public function test_admin_campaign_routes_remain_protected_behind_authentication(): void
    {
        $this->get('/admin/campaign/campaigns')->assertRedirect('/login');
    }
}
