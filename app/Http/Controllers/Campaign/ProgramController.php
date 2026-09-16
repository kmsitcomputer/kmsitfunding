<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign\Program;
use App\Models\Campaign\ProgramMediaAsset;
use App\Models\Rbac\Principal;
use App\Policies\ProgramPolicy;
use App\Services\Campaign\Exceptions\CampaignMediaValidationException;
use App\Services\Campaign\Exceptions\CampaignValidationException;
use App\Services\Campaign\ProgramMediaService;
use App\Services\Campaign\ProgramService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IMP-007 admin UI — thin controller mirroring ThemeController/Cms/*
 * Controller pattern exactly: resolve the acting Principal, authorize via
 * ProgramPolicy, delegate to ProgramService, surface exceptions as
 * validation-style errors. No business logic lives here.
 */
class ProgramController extends Controller
{
    public function index(Request $request, ProgramPolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->view($actor), 403);

        return Inertia::render('Campaign/Programs/Index', [
            'programs' => Program::query()->latest('updated_at')->paginate(20),
        ]);
    }

    public function create(Request $request, ProgramPolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->create($actor), 403);

        return Inertia::render('Campaign/Programs/Create');
    }

    public function store(Request $request, ProgramPolicy $policy, ProgramService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->create($actor), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description_html' => ['nullable', 'string', 'max:204800'],
        ]);

        try {
            $program = $service->create($validated, $actor);
        } catch (CampaignValidationException $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        return redirect()->route('campaign.programs.show', $program)->with('status', 'program-created');
    }

    public function show(Request $request, ProgramPolicy $policy, Program $program): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewProgram($actor, $program), 403);

        return Inertia::render('Campaign/Programs/Show', [
            'program' => $program,
            'campaigns' => $program->campaigns()->latest('updated_at')->get(),
            'mediaAssets' => $program->mediaAssets()->where('status', 'ACTIVE')->get(),
        ]);
    }

    public function update(Request $request, ProgramPolicy $policy, Program $program, ProgramService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $program), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description_html' => ['sometimes', 'nullable', 'string', 'max:204800'],
            'expected_edit_version' => ['required', 'integer'],
        ]);

        try {
            $service->update($program, $validated, $validated['expected_edit_version'], $actor);
        } catch (CampaignValidationException $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        return redirect()->route('campaign.programs.show', $program)->with('status', 'program-updated');
    }

    public function publish(Request $request, ProgramPolicy $policy, Program $program, ProgramService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->publish($actor, $program), 403);

        try {
            $service->publish($program, $actor);
        } catch (CampaignValidationException $e) {
            throw ValidationException::withMessages(['program' => $e->getMessage()]);
        }

        return redirect()->route('campaign.programs.show', $program)->with('status', 'program-published');
    }

    public function unpublish(Request $request, ProgramPolicy $policy, Program $program, ProgramService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->publish($actor, $program), 403);

        try {
            $service->unpublish($program, $actor);
        } catch (CampaignValidationException $e) {
            throw ValidationException::withMessages(['program' => $e->getMessage()]);
        }

        return redirect()->route('campaign.programs.show', $program)->with('status', 'program-unpublished');
    }

    public function archive(Request $request, ProgramPolicy $policy, Program $program, ProgramService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->archive($actor, $program), 403);

        try {
            $service->archive($program, $actor);
        } catch (CampaignValidationException $e) {
            throw ValidationException::withMessages(['program' => $e->getMessage()]);
        }

        return redirect()->route('campaign.programs.index')->with('status', 'program-archived');
    }

    public function uploadAsset(Request $request, ProgramPolicy $policy, Program $program, ProgramMediaService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->uploadAsset($actor), 403);

        $validated = $request->validate(['file' => ['required', 'file', 'max:5120']]);

        try {
            $service->upload($program, $validated['file'], $actor);
        } catch (CampaignMediaValidationException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return redirect()->route('campaign.programs.show', $program)->with('status', 'asset-uploaded');
    }

    public function archiveAsset(Request $request, ProgramPolicy $policy, ProgramMediaAsset $asset, ProgramMediaService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->archiveAsset($actor, $asset->program), 403);

        $service->archive($asset, $actor);

        return redirect()->route('campaign.programs.show', $asset->program)->with('status', 'asset-archived');
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }
}
