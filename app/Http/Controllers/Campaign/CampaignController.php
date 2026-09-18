<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignMediaAsset;
use App\Models\Campaign\Fund;
use App\Models\Campaign\Program;
use App\Models\Rbac\Principal;
use App\Policies\CampaignPolicy;
use App\Services\Campaign\CampaignEligibilityResolver;
use App\Services\Campaign\CampaignLifecycleService;
use App\Services\Campaign\CampaignMediaService;
use App\Services\Campaign\CampaignMediaTokenResolver;
use App\Services\Campaign\CampaignService;
use App\Services\Campaign\Exceptions\CampaignMediaValidationException;
use App\Services\Campaign\Exceptions\CampaignTransitionConflictException;
use App\Services\Campaign\Exceptions\CampaignValidationException;
use App\Services\Rbac\PrincipalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IMP-007 admin UI — thin controller for Campaign CRUD + lifecycle
 * transitions (docs/implementation/IMP-007-campaign-program-fund.md
 * section 21). `submit`/`approve`/`publish`/`reject`/`close` are five
 * distinct endpoints, each gated by its own CampaignPolicy method
 * (HD-IMP007-01) — never combined.
 */
class CampaignController extends Controller
{
    public function index(Request $request, CampaignPolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->view($actor), 403);

        return Inertia::render('Campaign/Campaigns/Index', [
            'campaigns' => Campaign::query()->latest('updated_at')->paginate(20),
        ]);
    }

    public function create(Request $request, CampaignPolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->create($actor), 403);

        return Inertia::render('Campaign/Campaigns/Create', [
            'programs' => Program::query()->where('status', 'PUBLISHED')->get(['ulid', 'name']),
        ]);
    }

    public function store(Request $request, CampaignPolicy $policy, CampaignService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->create($actor), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description_html' => ['nullable', 'string', 'max:204800'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'program_ulid' => ['nullable', 'string', 'size:26'],
            'target_amount_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        try {
            $campaign = $service->create($validated, $actor);
        } catch (CampaignValidationException $e) {
            throw ValidationException::withMessages([$e->reason => $e->getMessage()]);
        }

        return redirect()->route('campaign.campaigns.show', $campaign)->with('status', 'campaign-created');
    }

    public function show(Request $request, CampaignPolicy $policy, Campaign $campaign, CampaignEligibilityResolver $eligibility, CampaignMediaTokenResolver $tokenResolver): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewCampaign($actor, $campaign), 403);

        return Inertia::render('Campaign/Campaigns/Show', [
            'campaign' => $campaign,
            'is_donation_eligible' => $eligibility->isDonationEligible($campaign),
            'mediaAssets' => $campaign->mediaAssets()->where('status', 'ACTIVE')->get()->map(fn ($asset) => [
                'ulid' => $asset->ulid,
                'original_filename' => $asset->original_filename,
                'url' => $tokenResolver->resolveUrl($asset->ulid),
            ]),
            'funds' => Fund::query()->where('status', 'ACTIVE')->get(['ulid', 'name']),
            'currentFund' => $campaign->fund()->first(['ulid', 'name']),
        ]);
    }

    public function update(Request $request, CampaignPolicy $policy, Campaign $campaign, CampaignService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $campaign), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description_html' => ['sometimes', 'nullable', 'string', 'max:204800'],
            'purpose' => ['sometimes', 'nullable', 'string', 'max:500'],
            'program_ulid' => ['sometimes', 'nullable', 'string', 'size:26'],
            'fund_ulid' => ['sometimes', 'nullable', 'string', 'size:26'],
            'target_amount_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'expected_edit_version' => ['required', 'integer'],
        ]);

        try {
            $service->update($campaign, $validated, $validated['expected_edit_version'], $actor);
        } catch (CampaignValidationException $e) {
            throw ValidationException::withMessages([$e->reason => $e->getMessage()]);
        }

        return redirect()->route('campaign.campaigns.show', $campaign)->with('status', 'campaign-updated');
    }

    public function submit(Request $request, CampaignPolicy $policy, Campaign $campaign, CampaignLifecycleService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->submit($actor, $campaign), 403);

        try {
            $service->submit($campaign, $actor);
        } catch (CampaignTransitionConflictException $e) {
            throw ValidationException::withMessages(['campaign' => $e->getMessage()]);
        }

        return redirect()->route('campaign.campaigns.show', $campaign)->with('status', 'campaign-submitted');
    }

    public function approve(Request $request, CampaignPolicy $policy, Campaign $campaign, CampaignLifecycleService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->approve($actor, $campaign), 403);

        try {
            $service->approve($campaign, $actor);
        } catch (CampaignTransitionConflictException $e) {
            throw ValidationException::withMessages(['campaign' => $e->getMessage()]);
        }

        return redirect()->route('campaign.campaigns.show', $campaign)->with('status', 'campaign-approved');
    }

    public function reject(Request $request, CampaignPolicy $policy, Campaign $campaign, CampaignLifecycleService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->approve($actor, $campaign), 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $service->reject($campaign, $validated['reason'], $actor);
        } catch (CampaignTransitionConflictException $e) {
            throw ValidationException::withMessages(['campaign' => $e->getMessage()]);
        }

        return redirect()->route('campaign.campaigns.show', $campaign)->with('status', 'campaign-rejected');
    }

    public function publish(Request $request, CampaignPolicy $policy, Campaign $campaign, CampaignLifecycleService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->publish($actor, $campaign), 403);

        try {
            $service->publish($campaign, $actor);
        } catch (CampaignTransitionConflictException $e) {
            throw ValidationException::withMessages(['campaign' => $e->getMessage()]);
        }

        return redirect()->route('campaign.campaigns.show', $campaign)->with('status', 'campaign-published');
    }

    public function close(Request $request, CampaignPolicy $policy, Campaign $campaign, CampaignLifecycleService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->close($actor, $campaign), 403);

        try {
            $service->close($campaign, $actor);
        } catch (CampaignTransitionConflictException $e) {
            throw ValidationException::withMessages(['campaign' => $e->getMessage()]);
        }

        return redirect()->route('campaign.campaigns.show', $campaign)->with('status', 'campaign-closed');
    }

    public function uploadAsset(Request $request, CampaignPolicy $policy, Campaign $campaign, CampaignMediaService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->uploadAsset($actor), 403);

        $validated = $request->validate(['file' => ['required', 'file', 'max:5120']]);

        try {
            $service->upload($campaign, $validated['file'], $actor);
        } catch (CampaignMediaValidationException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return redirect()->route('campaign.campaigns.show', $campaign)->with('status', 'asset-uploaded');
    }

    public function archiveAsset(Request $request, CampaignPolicy $policy, CampaignMediaAsset $asset, CampaignMediaService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->archiveAsset($actor, $asset->campaign), 403);

        $service->archive($asset, $actor);

        return redirect()->route('campaign.campaigns.show', $asset->campaign)->with('status', 'asset-archived');
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }
}
