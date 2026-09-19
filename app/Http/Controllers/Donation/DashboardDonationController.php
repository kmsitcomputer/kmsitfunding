<?php

namespace App\Http\Controllers\Donation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Donation\StoreRecurringPlanRequest;
use App\Models\Campaign\Campaign;
use App\Models\Donation\Donation;
use App\Models\Donation\DonationRecurringPlan;
use App\Models\Rbac\Principal;
use App\Policies\DonationPolicy;
use App\Policies\RecurringPlanPolicy;
use App\Services\Donation\DonationTransitionService;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Donation\Exceptions\DonationValidationException;
use App\Services\Donation\RecurringPlanService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IMP-008 donor-owned Donation views/actions (docs/implementation/
 * IMP-008-donation.md "API Impact": GET /me/donations, GET
 * /me/donations/{ulid}, POST /me/donations/{ulid}/cancel). OWN scope
 * throughout — a donor sees/cancels only their own Donations. Guest
 * has no listing path (no account — a direct consequence, not a gap).
 * There is NO guest self-service cancellation endpoint anywhere in
 * v1 (HD-IMP008-05B) — this controller is authenticated-only.
 */
class DashboardDonationController extends Controller
{
    public function index(Request $request, DonationPolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);

        $donations = Donation::query()
            ->where('donor_principal_id', $actor->id)
            ->latest('id')
            ->paginate(20)
            ->through(fn (Donation $donation) => $this->ownPayload($donation));

        return Inertia::render('Donation/Index', [
            'donations' => $donations,
        ]);
    }

    public function show(Request $request, DonationPolicy $policy, Donation $donation): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewOwn($actor, $donation), 403);

        return Inertia::render('Donation/Show', [
            'donation' => $this->ownPayload($donation),
        ]);
    }

    public function cancel(Request $request, DonationPolicy $policy, Donation $donation, DonationTransitionService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->cancelOwn($actor, $donation), 403);

        try {
            $service->cancel($donation, $actor);
        } catch (DonationTransitionConflictException $e) {
            throw ValidationException::withMessages(['donation' => $e->getMessage()]);
        }

        return redirect()->route('donations.show', $donation)->with('status', 'donation-cancelled');
    }

    public function indexPlans(Request $request, RecurringPlanPolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);

        $plans = DonationRecurringPlan::query()
            ->where('donor_principal_id', $actor->id)
            ->latest('id')
            ->paginate(20)
            ->through(fn (DonationRecurringPlan $plan) => $this->planPayload($plan));

        return Inertia::render('Donation/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function storePlan(
        StoreRecurringPlanRequest $request,
        RecurringPlanPolicy $policy,
        RecurringPlanService $service,
    ): RedirectResponse {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->create($actor), 403);

        $validated = $request->validated();
        $campaign = Campaign::where('ulid', $request->input('campaign_ulid'))->firstOrFail();

        try {
            $plan = $service->create($campaign, $validated, $actor);
        } catch (DonationValidationException $e) {
            throw ValidationException::withMessages([$e->reason => $e->getMessage()]);
        }

        return redirect()->route('donations.plans.show', $plan)->with('status', 'recurring-plan-created');
    }

    public function showPlan(Request $request, RecurringPlanPolicy $policy, DonationRecurringPlan $plan): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewOwn($actor, $plan), 403);

        return Inertia::render('Donation/Plans/Show', [
            'plan' => $this->planPayload($plan),
        ]);
    }

    public function pausePlan(Request $request, RecurringPlanPolicy $policy, DonationRecurringPlan $plan, RecurringPlanService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->pauseOwn($actor, $plan), 403);

        try {
            $service->pause($plan, $actor);
        } catch (DonationTransitionConflictException $e) {
            throw ValidationException::withMessages(['plan' => $e->getMessage()]);
        }

        return redirect()->route('donations.plans.show', $plan)->with('status', 'recurring-plan-paused');
    }

    public function resumePlan(Request $request, RecurringPlanPolicy $policy, DonationRecurringPlan $plan, RecurringPlanService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->resumeOwn($actor, $plan), 403);

        try {
            $service->resume($plan, $actor);
        } catch (DonationTransitionConflictException $e) {
            throw ValidationException::withMessages(['plan' => $e->getMessage()]);
        }

        return redirect()->route('donations.plans.show', $plan)->with('status', 'recurring-plan-resumed');
    }

    public function cancelPlan(Request $request, RecurringPlanPolicy $policy, DonationRecurringPlan $plan, RecurringPlanService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->cancelOwn($actor, $plan), 403);

        try {
            $service->cancel($plan, $actor);
        } catch (DonationTransitionConflictException $e) {
            throw ValidationException::withMessages(['plan' => $e->getMessage()]);
        }

        return redirect()->route('donations.plans.show', $plan)->with('status', 'recurring-plan-cancelled');
    }

    /**
     * @return array<string, mixed>
     */
    private function ownPayload(Donation $donation): array
    {
        return [
            'ulid' => $donation->ulid,
            'status' => $donation->status,
            'amount_minor' => $donation->amount_minor,
            'currency' => $donation->currency,
            'is_anonymous' => $donation->is_anonymous,
            'donor_display_name' => $donation->donor_display_name,
            'campaign_id' => $donation->campaign_id,
            'created_at' => $donation->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planPayload(DonationRecurringPlan $plan): array
    {
        return [
            'ulid' => $plan->ulid,
            'status' => $plan->status,
            'frequency' => $plan->frequency,
            'amount_minor' => $plan->amount_minor,
            'currency' => $plan->currency,
            'is_anonymous' => $plan->is_anonymous,
            'starts_at' => $plan->starts_at,
            'ends_at' => $plan->ends_at,
            'next_occurrence_at' => $plan->next_occurrence_at,
        ];
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }
}
