<?php

namespace App\Http\Controllers;

use App\Http\Requests\Donation\StoreDonationRequest;
use App\Models\Campaign\Campaign;
use App\Models\Donation\Donation;
use App\Services\Campaign\CampaignEligibilityResolver;
use App\Services\Donation\DonationService;
use App\Services\Donation\Exceptions\DonationTransitionConflictException;
use App\Services\Donation\Exceptions\DonationValidationException;
use App\Services\Rbac\PrincipalService;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IMP-008 public Donation entry point (docs/implementation/
 * IMP-008-donation.md "API Impact": POST /campaigns/{slug}/donations —
 * the intentional public/guest-or-authenticated creation endpoint, plus
 * the donation form rendered on the Campaign page when
 * CampaignEligibilityResolver::isDonationEligible() is true).
 *
 * A guest caller (no authenticated user) creates a guest Donation
 * (donor_principal_id null, ADR-002 Unauthenticated audit attribution);
 * an authenticated caller creates an authenticated Donation (donor
 * derived from their own Principal — never caller-set). No business
 * logic lives here — all delegation to DonationService.
 *
 * Identity rendering: authenticated rows expose donor_display_name only
 * when is_anonymous is false; guest_name/guest_email (BR-2 contact
 * fields, not public identity) never leave the server.
 */
class PublicDonationController extends Controller
{
    public function create(Campaign $campaign, CampaignEligibilityResolver $eligibility)
    {
        abort_unless($campaign->status === 'PUBLISHED', 404);

        return Inertia::render('Public/DonationCreate', [
            'campaign' => [
                'ulid' => $campaign->ulid,
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'summary' => $campaign->summary,
            ],
            'is_donation_eligible' => $eligibility->isDonationEligible($campaign),
        ]);
    }

    public function store(
        StoreDonationRequest $request,
        Campaign $campaign,
        DonationService $service,
        CampaignEligibilityResolver $eligibility,
        PrincipalService $principals,
    ): RedirectResponse {
        abort_unless($campaign->status === 'PUBLISHED', 404);

        // BR-1 fail-closed at the HTTP boundary: the service re-checks
        // authoritatively (a re-derived gate here would violate the
        // MUST-NOT-re-derive rule, so this delegates to the resolver —
        // the same instance the service consumes).
        abort_unless($eligibility->isDonationEligible($campaign), 422);

        $validated = $request->validated();
        $idempotencyKey = $request->idempotencyKey();

        $user = $request->user();
        $actor = $user !== null ? $principals->forUser($user) : null;

        try {
            $donation = $service->create($campaign, $validated, $actor, $idempotencyKey);
        } catch (DonationValidationException $e) {
            throw ValidationException::withMessages([$e->reason => $e->getMessage()]);
        } catch (DonationTransitionConflictException $e) {
            throw ValidationException::withMessages(['idempotency_key' => $e->getMessage()]);
        }

        return redirect()->route('public.donations.receipt', [
            'campaign' => $campaign->slug,
            'donation' => $donation->ulid,
        ])->with('status', 'donation-created');
    }

    public function receipt(Campaign $campaign, Donation $donation): Response
    {
        abort_unless($donation->campaign_id === $campaign->id, 404);

        return Inertia::render('Public/DonationReceipt', [
            'donation' => $this->publicPayload($donation),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicPayload(Donation $donation): array
    {
        return [
            'ulid' => $donation->ulid,
            'status' => $donation->status,
            'amount_minor' => $donation->amount_minor,
            'currency' => $donation->currency,
            'formatted_amount' => Money::ofMinorUnits($donation->amount_minor, $donation->currency)->format(),
            'is_anonymous' => $donation->is_anonymous,
            'donor_display_name' => $donation->is_anonymous ? null : $donation->donor_display_name,
        ];
    }
}
