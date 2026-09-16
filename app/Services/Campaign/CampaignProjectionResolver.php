<?php

namespace App\Services\Campaign;

use App\Models\Campaign\Campaign;
use App\Support\Money\Money;

/**
 * IMP-007 — the "IMP-007+ projection contract" IMP-005 §16 explicitly
 * deferred to this stage ("any future 'latest campaigns block' style
 * feature consumes a controlled projection PROVIDED by the business
 * domain — the projection contract is created by that domain's stage
 * (IMP-007+), never by CMS reaching into domain tables"). Read-only.
 *
 * Wired into the locked Theme Engine's content_list/card_grid components
 * under a targeted, additive Human-authorized IMP-006 amendment (the
 * original scope note about this being a future proposal is preserved
 * here for history — see docs/adr/ADR-001-theme-content-projection-
 * amendment.md for the amendment record). No Campaign business truth is
 * copied into any Theme table/JSON — PublicRenderer calls this resolver
 * at render time, every time.
 *
 * Availability: donation-eligibility (HD-IMP007-03) is computed via the
 * canonical CampaignEligibilityResolver — never re-derived from
 * starts_at/ends_at here or in Theme code, per the Human authorization's
 * explicit instruction. Visibility in this list is status-only
 * (PUBLISHED), matching the public Campaign index page's own precedent —
 * a not-currently-eligible PUBLISHED campaign still appears, with
 * `is_donation_eligible: false` in its metadata for the presentation
 * layer to display accordingly. No financial figures beyond the
 * non-authoritative target amount are ever included (no collected
 * amount, donor count, or progress percentage — IMP-008 does not exist).
 */
final class CampaignProjectionResolver
{
    /**
     * @return array<int, array{ulid:string,title:string,summary:?string,image_url:?string,url:string,metadata:array<string,mixed>}>
     */
    public function latestPublished(int $limit = 6): array
    {
        $eligibility = new CampaignEligibilityResolver;

        return Campaign::query()
            ->where('status', 'PUBLISHED')
            ->with('program:id,name')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Campaign $campaign) => [
                'ulid' => $campaign->ulid,
                'title' => $campaign->name,
                'summary' => $campaign->summary,
                'image_url' => $this->coverImageUrl($campaign),
                'url' => route('public.campaigns.show', $campaign),
                'metadata' => [
                    'program_name' => $campaign->program?->name,
                    'formatted_target_amount' => $this->formattedTargetAmount($campaign),
                    'starts_at' => $campaign->starts_at,
                    'ends_at' => $campaign->ends_at,
                    'is_donation_eligible' => $eligibility->isDonationEligible($campaign),
                ],
            ])
            ->all();
    }

    private function coverImageUrl(Campaign $campaign): ?string
    {
        $asset = $campaign->mediaAssets()->where('status', 'ACTIVE')->oldest('id')->first();

        return $asset !== null ? app(CampaignMediaTokenResolver::class)->resolveUrl($asset->ulid) : null;
    }

    private function formattedTargetAmount(Campaign $campaign): ?string
    {
        if ($campaign->target_amount_minor === null || $campaign->currency === null) {
            return null;
        }

        return Money::ofMinorUnits($campaign->target_amount_minor, $campaign->currency)->format();
    }
}
