<?php

namespace Tests\Support\Donation;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\Fund;
use App\Models\Rbac\Principal;
use App\Services\Campaign\CampaignLifecycleService;
use App\Services\Campaign\CampaignService;
use App\Services\Campaign\FundService;
use Illuminate\Support\Str;

/**
 * IMP-008 test-only fixture helper: builds an eligible (PUBLISHED, open
 * window) Campaign through the real CampaignService + lifecycle path —
 * never raw status writes — so eligibility tests exercise the genuine
 * CampaignEligibilityResolver contract.
 */
trait MakesDonationCampaigns
{
    private int $donationFixtureSequence = 0;

    private function makeEligibleCampaign(Principal $actor, array $overrides = []): Campaign
    {
        $this->donationFixtureSequence++;

        $fund = app(FundService::class)->create([
            'name' => 'Donation Fund '.$this->donationFixtureSequence,
            'code' => 'don-fund-'.$this->donationFixtureSequence.'-'.uniqid(),
        ], $actor);

        $campaign = app(CampaignService::class)->create(array_merge([
            'name' => 'Donation Campaign '.$this->donationFixtureSequence,
            'slug' => 'donation-campaign-'.$this->donationFixtureSequence.'-'.uniqid(),
        ], $overrides), $actor);

        app(CampaignService::class)->update($campaign, ['fund_ulid' => $fund->ulid], 0, $actor);

        $lifecycle = app(CampaignLifecycleService::class);
        $lifecycle->submit($campaign->fresh(), $actor);
        $lifecycle->approve($campaign->fresh(), $actor);
        $lifecycle->publish($campaign->fresh(), $actor);

        return $campaign->fresh();
    }

    private function makeCampaignAtStatus(Principal $actor, string $status): Campaign
    {
        $this->donationFixtureSequence++;

        $campaign = new Campaign;
        $campaign->forceFill([
            'ulid' => (string) Str::ulid(),
            'name' => 'C '.$this->donationFixtureSequence,
            'slug' => 'c-'.$this->donationFixtureSequence.'-'.uniqid(),
            'status' => $status,
            'edit_version' => 0,
            'created_by_principal_id' => $actor->id,
            'updated_by_principal_id' => $actor->id,
        ]);
        $campaign->save();

        return $campaign;
    }

    private function makeFund(Principal $actor): Fund
    {
        $this->donationFixtureSequence++;

        return app(FundService::class)->create([
            'name' => 'Donation Fund '.$this->donationFixtureSequence,
            'code' => 'don-fund-'.$this->donationFixtureSequence.'-'.uniqid(),
        ], $actor);
    }
}
