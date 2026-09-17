<?php

namespace App\Services\Campaign;

use App\Models\Campaign\Campaign;
use Carbon\CarbonInterface;

/**
 * IMP-007 (HD-IMP007-03) — the ONE canonical, reusable contract answering
 * "is this Campaign currently donation-eligible", so IMP-008 never
 * re-derives this formula independently (docs/implementation/
 * IMP-007-campaign-program-fund.md section 8b). Pure read/query function —
 * NEVER mutates status. Null semantics: a null starts_at/ends_at means no
 * bound on that side. Boundary semantics: both comparisons are inclusive.
 */
final class CampaignEligibilityResolver
{
    public function isDonationEligible(Campaign $campaign, ?CarbonInterface $at = null): bool
    {
        if ($campaign->status !== 'PUBLISHED') {
            return false;
        }

        $at ??= now();

        if ($campaign->starts_at !== null && $at->lt($campaign->starts_at)) {
            return false;
        }

        if ($campaign->ends_at !== null && $at->gt($campaign->ends_at)) {
            return false;
        }

        return true;
    }
}
