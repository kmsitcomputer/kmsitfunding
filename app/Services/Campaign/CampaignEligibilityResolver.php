<?php

namespace App\Services\Campaign;

use App\Models\Campaign\Campaign;
use Carbon\Carbon;
use DateTimeInterface;

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
    public function isDonationEligible(Campaign $campaign, ?DateTimeInterface $at = null): bool
    {
        if ($campaign->status !== 'PUBLISHED') {
            return false;
        }

        // The documented public contract takes ANY DateTimeInterface (section
        // 8b), not merely Carbon — normalizing here keeps the canonical
        // reusable contract consumable by a future IMP-008 caller passing a
        // plain DateTimeImmutable, without changing the comparison semantics.
        $at = $at === null ? now() : Carbon::instance($at);

        if ($campaign->starts_at !== null && $at->lt($campaign->starts_at)) {
            return false;
        }

        if ($campaign->ends_at !== null && $at->gt($campaign->ends_at)) {
            return false;
        }

        return true;
    }
}
