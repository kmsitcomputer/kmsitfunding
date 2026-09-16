<?php

namespace App\Services\Campaign;

use App\Models\Campaign\Campaign;

/**
 * IMP-007 — the "IMP-007+ projection contract" IMP-005 §16 explicitly
 * deferred to this stage ("any future 'latest campaigns block' style
 * feature consumes a controlled projection PROVIDED by the business
 * domain — the projection contract is created by that domain's stage
 * (IMP-007+), never by CMS reaching into domain tables"). Read-only,
 * minimal DTO shape; not yet wired into any Theme component (that would be
 * a separate, future LOCKED-CONTRACT proposal against IMP-006's closed
 * content_kind list — see the specification section 4 Non-Goals).
 */
final class CampaignProjectionResolver
{
    /**
     * @return array<int, array{ulid:string,title:string,summary:?string,is_donation_eligible:bool}>
     */
    public function latestPublished(int $limit = 6): array
    {
        $eligibility = new CampaignEligibilityResolver;

        return Campaign::query()
            ->where('status', 'PUBLISHED')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Campaign $campaign) => [
                'ulid' => $campaign->ulid,
                'title' => $campaign->name,
                'summary' => $campaign->summary,
                'is_donation_eligible' => $eligibility->isDonationEligible($campaign),
            ])
            ->all();
    }
}
