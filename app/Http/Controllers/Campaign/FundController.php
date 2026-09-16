<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign\Fund;
use App\Models\Rbac\Principal;
use App\Policies\FundPolicy;
use App\Services\Campaign\Exceptions\FundValidationException;
use App\Services\Campaign\FundService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IMP-007 admin UI — thin controller for Fund CRUD, mirroring
 * ProgramController exactly.
 */
class FundController extends Controller
{
    public function index(Request $request, FundPolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->view($actor), 403);

        return Inertia::render('Campaign/Funds/Index', [
            'funds' => Fund::query()->latest('updated_at')->paginate(20),
        ]);
    }

    public function create(Request $request, FundPolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->create($actor), 403);

        return Inertia::render('Campaign/Funds/Create');
    }

    public function store(Request $request, FundPolicy $policy, FundService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->create($actor), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50'],
            'restriction_note' => ['nullable', 'string'],
        ]);

        try {
            $fund = $service->create($validated, $actor);
        } catch (FundValidationException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        return redirect()->route('campaign.funds.index')->with('status', 'fund-created')->with('fund_ulid', $fund->ulid);
    }

    public function edit(Request $request, FundPolicy $policy, Fund $fund): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $fund), 403);

        return Inertia::render('Campaign/Funds/Edit', ['fund' => $fund]);
    }

    public function update(Request $request, FundPolicy $policy, Fund $fund, FundService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->update($actor, $fund), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'restriction_note' => ['sometimes', 'nullable', 'string'],
        ]);

        $service->update($fund, $validated, $actor);

        return redirect()->route('campaign.funds.edit', $fund)->with('status', 'fund-updated');
    }

    public function archive(Request $request, FundPolicy $policy, Fund $fund, FundService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->archive($actor, $fund), 403);

        $service->archive($fund, $actor);

        return redirect()->route('campaign.funds.index')->with('status', 'fund-archived');
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }
}
