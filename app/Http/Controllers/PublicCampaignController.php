<?php

namespace App\Http\Controllers;

use App\Models\Campaign\Campaign;
use App\Services\Campaign\CampaignEligibilityResolver;
use App\Services\Campaign\CampaignMediaTokenResolver;
use App\Services\Theme\PublicRenderer;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * IMP-007 — public Campaign pages (docs/implementation/
 * IMP-007-campaign-program-fund.md sections 8b/14/20/21). Visibility is
 * status-only (PUBLISHED); donation-eligibility (is_donation_eligible) is
 * computed separately via CampaignEligibilityResolver and passed as display
 * data — HD-IMP007-03: a PUBLISHED-but-not-yet-open/ended Campaign remains
 * reachable, never hidden.
 */
class PublicCampaignController extends Controller
{
    public function index(Request $request, CampaignEligibilityResolver $eligibility, CampaignMediaTokenResolver $tokenResolver)
    {
        $campaigns = Campaign::query()
            ->where('status', 'PUBLISHED')
            ->with('program:id,name')
            ->orderByDesc('published_at')
            ->paginate(12);

        // through() maps each row to a safe field allow-list before
        // serialization — the internal BIGINT id never reaches this public
        // listing response.
        $campaigns->through(fn (Campaign $campaign) => [
            'ulid' => $campaign->ulid,
            'slug' => $campaign->slug,
            'name' => $campaign->name,
            'summary' => $campaign->summary,
            'program_name' => $campaign->program?->name,
            'formatted_target_amount' => $this->formattedTargetAmount($campaign),
            'is_donation_eligible' => $eligibility->isDonationEligible($campaign),
            'cover_image_url' => $this->coverImageUrl($campaign, $tokenResolver),
        ]);

        return Inertia::render('Public/CampaignIndex', [
            'campaigns' => $campaigns,
        ]);
    }

    public function show(Request $request, Campaign $campaign, PublicRenderer $renderer, CampaignEligibilityResolver $eligibility, CampaignMediaTokenResolver $tokenResolver)
    {
        abort_unless($campaign->status === 'PUBLISHED', 404);

        $theme = $renderer->activeTheme();

        return Inertia::render('Public/CampaignShow', [
            // Explicit field allow-list, not the raw model — the internal
            // BIGINT id (and every other server-only column) must never
            // reach a public response (AC-007-011).
            'campaign' => [
                'ulid' => $campaign->ulid,
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'summary' => $campaign->summary,
                'description_html' => $campaign->description_html,
                'purpose' => $campaign->purpose,
                'starts_at' => $campaign->starts_at,
                'ends_at' => $campaign->ends_at,
                'program_name' => $campaign->program?->name,
                'cover_image_url' => $this->coverImageUrl($campaign, $tokenResolver),
            ],
            'is_donation_eligible' => $eligibility->isDonationEligible($campaign),
            'formatted_target_amount' => $this->formattedTargetAmount($campaign),
            'branding' => $this->brandingPayload($theme),
        ]);
    }

    private function coverImageUrl(Campaign $campaign, CampaignMediaTokenResolver $tokenResolver): ?string
    {
        $asset = $campaign->mediaAssets()->where('status', 'ACTIVE')->oldest('id')->first();

        return $asset !== null ? $tokenResolver->resolveUrl($asset->ulid) : null;
    }

    private function formattedTargetAmount(Campaign $campaign): ?string
    {
        if ($campaign->target_amount_minor === null || $campaign->currency === null) {
            return null;
        }

        return Money::ofMinorUnits($campaign->target_amount_minor, $campaign->currency)->format();
    }

    private function brandingPayload(mixed $theme): array
    {
        $branding = $theme?->branding;

        if ($branding === null) {
            return ['color_tokens' => [], 'font_family' => 'system'];
        }

        return [
            'color_tokens' => $branding->color_tokens,
            'font_family' => $branding->font_family,
        ];
    }
}
