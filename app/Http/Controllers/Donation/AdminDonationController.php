<?php

namespace App\Http\Controllers\Donation;

use App\Http\Controllers\Controller;
use App\Models\Donation\Donation;
use App\Models\Donation\DonationRecurringPlan;
use App\Models\Rbac\Principal;
use App\Policies\DonationPolicy;
use App\Policies\RecurringPlanPolicy;
use App\Services\Donation\DonationTransitionService;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Donation\RecurringPlanService;
use App\Services\Rbac\PrincipalService;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IMP-008 admin/backoffice Donation views/actions
 * (docs/implementation/IMP-008-donation.md "API Impact": GET+POST
 * /admin/donation/*). ORGANIZATION scope throughout — the same path
 * handles the guest-cancellation support request (HD-IMP008-05B/BR-13:
 * no separate guest mechanism) and the recurring-plan admin override
 * (HD-IMP008-03). Internal BIGINT ids never reach the response
 * (ULID-bound route keys, explicit field allow-lists).
 */
class AdminDonationController extends Controller
{
    public function index(Request $request, DonationPolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewAny($actor), 403);

        $donations = Donation::query()
            ->latest('id')
            ->paginate(20)
            ->through(fn (Donation $donation) => $this->adminPayload($donation));

        return Inertia::render('Donation/Admin/Index', [
            'donations' => $donations,
        ]);
    }

    public function show(Request $request, DonationPolicy $policy, Donation $donation): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewDonation($actor, $donation), 403);

        return Inertia::render('Donation/Admin/Show', [
            'donation' => $this->adminPayload($donation),
        ]);
    }

    public function cancel(Request $request, DonationPolicy $policy, Donation $donation, DonationTransitionService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->cancelAny($actor, $donation), 403);

        try {
            $service->cancel($donation, $actor);
        } catch (DonationTransitionConflictException $e) {
            throw ValidationException::withMessages(['donation' => $e->getMessage()]);
        }

        return redirect()->route('donation.admin.show', $donation)->with('status', 'donation-cancelled');
    }

    public function showPlan(Request $request, RecurringPlanPolicy $policy, DonationRecurringPlan $plan): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewAny($actor), 403);

        return Inertia::render('Donation/Admin/PlanShow', [
            'plan' => $this->adminPlanPayload($plan),
        ]);
    }

    public function pausePlan(Request $request, RecurringPlanPolicy $policy, DonationRecurringPlan $plan, RecurringPlanService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->pauseAny($actor, $plan), 403);

        try {
            $service->pause($plan, $actor);
        } catch (DonationTransitionConflictException $e) {
            throw ValidationException::withMessages(['plan' => $e->getMessage()]);
        }

        return redirect()->route('donation.admin.plans.show', $plan)->with('status', 'recurring-plan-paused');
    }

    public function resumePlan(Request $request, RecurringPlanPolicy $policy, DonationRecurringPlan $plan, RecurringPlanService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->resumeAny($actor, $plan), 403);

        try {
            $service->resume($plan, $actor);
        } catch (DonationTransitionConflictException $e) {
            throw ValidationException::withMessages(['plan' => $e->getMessage()]);
        }

        return redirect()->route('donation.admin.plans.show', $plan)->with('status', 'recurring-plan-resumed');
    }

    public function cancelPlan(Request $request, RecurringPlanPolicy $policy, DonationRecurringPlan $plan, RecurringPlanService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->cancelAny($actor, $plan), 403);

        try {
            $service->cancel($plan, $actor);
        } catch (DonationTransitionConflictException $e) {
            throw ValidationException::withMessages(['plan' => $e->getMessage()]);
        }

        return redirect()->route('donation.admin.plans.show', $plan)->with('status', 'recurring-plan-cancelled');
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPayload(Donation $donation): array
    {
        return [
            'ulid' => $donation->ulid,
            'status' => $donation->status,
            'amount_minor' => $donation->amount_minor,
            'currency' => $donation->currency,
            'formatted_amount' => Money::ofMinorUnits($donation->amount_minor, $donation->currency)->format(),
            'is_anonymous' => $donation->is_anonymous,
            'is_guest' => $donation->donor_principal_id === null,
            'donor_display_name' => $donation->donor_display_name,
            'guest_name' => $donation->guest_name,
            'guest_email' => $donation->guest_email,
            'campaign_id' => $donation->campaign_id,
            'created_at' => $donation->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPlanPayload(DonationRecurringPlan $plan): array
    {
        return [
            'ulid' => $plan->ulid,
            'status' => $plan->status,
            'frequency' => $plan->frequency,
            'amount_minor' => $plan->amount_minor,
            'currency' => $plan->currency,
            'formatted_amount' => Money::ofMinorUnits($plan->amount_minor, $plan->currency)->format(),
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
