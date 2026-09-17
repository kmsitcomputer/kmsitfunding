<?php

namespace Tests\Unit\Campaign;

use App\Models\Campaign\Campaign;
use App\Services\Campaign\CampaignEligibilityResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Rbac\RbacTestActors;
use Tests\TestCase;

/**
 * IMP-007 (HD-IMP007-03) — the canonical donation-eligibility contract.
 * docs/implementation/IMP-007-campaign-program-fund.md section 8b.
 * AC-007-018/019.
 */
class CampaignEligibilityResolverTest extends TestCase
{
    use RbacTestActors;
    use RefreshDatabase;

    private function makeCampaign(array $overrides = []): Campaign
    {
        $actor = $this->makeUnauthorizedActor();

        $campaign = new Campaign;
        $campaign->forceFill(array_merge([
            'ulid' => (string) Str::ulid(),
            'name' => 'Test Campaign',
            'slug' => 'test-campaign-'.uniqid(),
            'status' => 'PUBLISHED',
            'edit_version' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ], $overrides));
        $campaign->save();

        return $campaign;
    }

    public function test_non_published_campaign_is_never_eligible_regardless_of_dates(): void
    {
        $resolver = new CampaignEligibilityResolver;
        $campaign = $this->makeCampaign(['status' => 'DRAFT']);

        $this->assertFalse($resolver->isDonationEligible($campaign));
    }

    public function test_both_bounds_null_is_eligible_at_any_time(): void
    {
        $resolver = new CampaignEligibilityResolver;
        $campaign = $this->makeCampaign(['starts_at' => null, 'ends_at' => null]);

        $this->assertTrue($resolver->isDonationEligible($campaign, Carbon::parse('2020-01-01')));
        $this->assertTrue($resolver->isDonationEligible($campaign, Carbon::parse('2099-01-01')));
    }

    public function test_only_starts_at_set_boundary_and_before_after(): void
    {
        $resolver = new CampaignEligibilityResolver;
        $campaign = $this->makeCampaign(['starts_at' => Carbon::parse('2026-06-01 00:00:00'), 'ends_at' => null]);

        $this->assertFalse($resolver->isDonationEligible($campaign, Carbon::parse('2026-05-31 23:59:59')));
        $this->assertTrue($resolver->isDonationEligible($campaign, Carbon::parse('2026-06-01 00:00:00')), 'Lower boundary is inclusive.');
        $this->assertTrue($resolver->isDonationEligible($campaign, Carbon::parse('2026-06-02 00:00:00')));
    }

    public function test_only_ends_at_set_boundary_and_before_after(): void
    {
        $resolver = new CampaignEligibilityResolver;
        $campaign = $this->makeCampaign(['starts_at' => null, 'ends_at' => Carbon::parse('2026-06-30 23:59:59')]);

        $this->assertTrue($resolver->isDonationEligible($campaign, Carbon::parse('2026-06-30 23:59:59')), 'Upper boundary is inclusive.');
        $this->assertFalse($resolver->isDonationEligible($campaign, Carbon::parse('2026-07-01 00:00:00')));
    }

    public function test_both_bounds_set_full_window_semantics(): void
    {
        $resolver = new CampaignEligibilityResolver;
        $campaign = $this->makeCampaign([
            'starts_at' => Carbon::parse('2026-06-01 00:00:00'),
            'ends_at' => Carbon::parse('2026-06-30 23:59:59'),
        ]);

        $this->assertFalse($resolver->isDonationEligible($campaign, Carbon::parse('2026-05-01 00:00:00')), 'Before window.');
        $this->assertTrue($resolver->isDonationEligible($campaign, Carbon::parse('2026-06-01 00:00:00')), 'At lower boundary.');
        $this->assertTrue($resolver->isDonationEligible($campaign, Carbon::parse('2026-06-15 00:00:00')), 'Inside window.');
        $this->assertTrue($resolver->isDonationEligible($campaign, Carbon::parse('2026-06-30 23:59:59')), 'At upper boundary.');
        $this->assertFalse($resolver->isDonationEligible($campaign, Carbon::parse('2026-07-01 00:00:00')), 'After window.');
    }

    public function test_resolver_never_mutates_persisted_status(): void
    {
        $resolver = new CampaignEligibilityResolver;
        $campaign = $this->makeCampaign([
            'starts_at' => Carbon::parse('2099-01-01'),
            'ends_at' => null,
        ]);

        $resolver->isDonationEligible($campaign, Carbon::now());

        $this->assertSame('PUBLISHED', $campaign->fresh()->status, 'Time passing alone must never mutate status.');
    }
}
