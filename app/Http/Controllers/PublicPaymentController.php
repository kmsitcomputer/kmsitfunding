<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManualTransfer\StoreEvidenceRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Donation\Donation;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentProviderCredential;
use App\Policies\PaymentPolicy;
use App\Services\Payment\Exceptions\PaymentTransitionConflictException;
use App\Services\Payment\Exceptions\PaymentValidationException;
use App\Services\Payment\ManualTransferEvidenceService;
use App\Services\Payment\PaymentCreationService;
use App\Services\Rbac\PrincipalService;
use App\Support\Money\Exceptions\UnknownCurrencyException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * IMP-009 public Payment entry point (docs/implementation/
 * IMP-009-payment-hub.md "Routes / API Boundary": POST
 * /donations/{ulid}/payments — the intentional
 * guest-or-authenticated creation endpoint).
 *
 * A guest caller (no authenticated user) creates against a guest
 * Donation (ADR-003 Unauthenticated audit attribution); an
 * authenticated caller creates against their own Donation (ownership
 * derived from their own Principal — never caller-set). No business
 * logic lives here — all delegation to PaymentCreationService. No
 * guest resume/retry mechanism exists beyond this creation call
 * (HD-IMP009-04): identifiers are never credentials.
 *
 * F-04/F-05 in-flow guest access: the creation response binds the new
 * Payment to THIS browser session via persistent server-side session
 * possession (session()->put() of the created Payment ULIDs — never
 * flash state, never a ULID/email bearer token, never the server-side
 * session id as a credential). The identifier-keyed GET
 * and the guest evidence POST below are reachable ONLY through that
 * session binding: knowing ULIDs alone exposes nothing, a different
 * anonymous session exposes nothing, and no
 * permanent bearer capability is ever minted. The binding is a
 * per-session set (Payment ULIDs created by THAT session only), so a
 * later legitimate guest Payment in the same session never invalidates
 * an earlier one; there is no cross-device/cross-session recovery
 * (HD-IMP009-04).
 */
class PublicPaymentController extends Controller
{
    public function store(
        StorePaymentRequest $request,
        Donation $donation,
        PaymentCreationService $service,
        PrincipalService $principals,
        PaymentPolicy $policy,
    ): RedirectResponse {
        abort_unless($donation->status === 'PENDING', 422);

        $idempotencyKey = $request->idempotencyKey();
        $validated = $request->validated();

        $this->assertProviderAvailable($validated['provider']);

        $user = $request->user();
        $actor = $user !== null ? $principals->forUser($user) : null;

        if ($actor !== null) {
            // F-02: authenticated creation enforces payment.create +
            // OWN scope through the canonical Policy (never a manual
            // re-derivation here) — the service's ownership check
            // remains as defense in depth.
            $probe = (new Payment)->forceFill(['donation_id' => $donation->id]);
            $probe->setRelation('donation', $donation);
            abort_unless($policy->createOwn($actor, $probe), 403);
        } else {
            // F-03: guest creation is allowed ONLY against a
            // guest-owned Donation (donor_principal_id NULL). An
            // unauthenticated actor MUST NOT create a Payment against
            // an authenticated donor-owned Donation.
            abort_unless($donation->donor_principal_id === null, 403);
        }

        try {
            $payment = $service->create($donation, $validated, $actor, $idempotencyKey);
        } catch (UnknownCurrencyException $e) {
            throw ValidationException::withMessages(['currency' => $e->getMessage()]);
        } catch (PaymentValidationException $e) {
            throw ValidationException::withMessages([$e->reason => $e->getMessage()]);
        } catch (PaymentTransitionConflictException $e) {
            throw ValidationException::withMessages(['idempotency_key' => $e->getMessage()]);
        }

        $this->rememberGuestPayment($request, $payment);

        return redirect()->route('public.payments.show', [
            'donation' => $donation->ulid,
            'payment' => $payment->ulid,
        ])->with('status', 'payment-created');
    }

    public function show(Request $request, Donation $donation, Payment $payment, PaymentPolicy $policy, PrincipalService $principals)
    {
        abort_unless($payment->donation_id === $donation->id, 404);

        $user = $request->user();

        if ($user !== null) {
            // Authenticated reads stay policy-based (OWN scope via the
            // owning Donation): no session possession is consulted or
            // required for a signed-in donor.
            $actor = $principals->forUser($user);
            abort_unless($policy->viewOwn($actor, $payment), 404);
        } else {
            // F-04: this identifier-keyed GET is creation-flow
            // information ONLY — reachable through the persistent
            // creation session binding, never through ULID knowledge
            // alone (a later visit, another browser, a leaked URL is
            // 404). Guest-owned Payment only, bound to a Payment this
            // session created. No Payment state, no instructions, and
            // in particular no Stripe client_secret, ever leaks
            // through an unrestricted identifier-keyed GET.
            abort_unless($donation->donor_principal_id === null, 404);
            abort_unless($this->sessionOwnsGuestPayment($request, $payment), 404);
        }

        return response()->json(self::publicPayload($payment));
    }

    public function storeEvidence(
        StoreEvidenceRequest $request,
        PaymentPolicy $policy,
        PrincipalService $principals,
        Donation $donation,
        Payment $payment,
        ManualTransferEvidenceService $service,
    ): RedirectResponse {
        abort_unless($payment->donation_id === $donation->id, 404);

        $user = $request->user();

        if ($user !== null) {
            $actor = $principals->forUser($user);
            abort_unless($policy->submitEvidence($actor, $payment), 403);
        } else {
            // F-05: guest evidence submission — the exact approved
            // guest-access shape, no bearer token invented: guest-owned
            // Payment only (donation without an authenticated owner),
            // reached through the persistent creation session binding
            // only (the same browser session that created the
            // Payment — possession survives ordinary intermediate
            // requests such as viewing instructions, performing the
            // transfer, and returning to upload evidence — but never
            // another session, never ULID knowledge alone).
            // Eligibility (manual-transfer provider, PENDING,
            // unreviewed, evidence-required flow) stays enforced by
            // ManualTransferEvidenceService itself.
            abort_unless($donation->donor_principal_id === null, 403);
            abort_unless($this->sessionOwnsGuestPayment($request, $payment), 403);
            $actor = null;
        }

        $validated = $request->validated();

        try {
            $service->submit($payment, $validated['evidence'], [
                'declared_amount_minor' => $validated['declared_amount_minor'] ?? null,
                'declared_currency' => $validated['declared_currency'] ?? null,
                'declared_transferred_at' => $validated['declared_transferred_at'] ?? null,
            ], $actor);
        } catch (PaymentValidationException $e) {
            throw ValidationException::withMessages([$e->reason => $e->getMessage()]);
        } catch (PaymentTransitionConflictException $e) {
            throw ValidationException::withMessages(['payment' => $e->getMessage()]);
        }

        return redirect()->route('public.payments.show', [
            'donation' => $donation->ulid,
            'payment' => $payment->ulid,
        ])->with('status', 'evidence-submitted');
    }

    /**
     * @return array<string, mixed>
     */
    public static function publicPayload(Payment $payment): array
    {
        return [
            'ulid' => $payment->ulid,
            'status' => $payment->status,
            'provider' => $payment->provider,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'instructions' => $payment->instructions_payload,
            'created_at' => $payment->created_at,
        ];
    }

    private function assertProviderAvailable(string $provider): void
    {
        if ($provider === 'manual_transfer') {
            return;
        }

        $credential = PaymentProviderCredential::query()->where('provider', $provider)->first();

        if ($credential === null || ! $credential->is_enabled) {
            throw ValidationException::withMessages(['provider' => 'This payment method is currently unavailable.']);
        }
    }

    /**
     * NEW-F-01 persistent guest session possession: the ULIDs of the
     * guest Payments THIS server-side session created. A set (never a
     * single slot) so a second legitimate guest Payment in the same
     * session never invalidates the first. Stored with session()->put()
     * — persistent across ordinary subsequent requests — never flash
     * state, never a bearer token, never an identifier-as-secret: the
     * ULID is only meaningful when presented from the possessing
     * session, alongside the guest-ownership gate at each call site.
     *
     * @return array<int, string>
     */
    private function guestPaymentUlids(Request $request): array
    {
        $ulids = $request->session()->get('guest_payment_ulids', []);

        return is_array($ulids) ? array_values(array_filter($ulids, 'is_string')) : [];
    }

    private function rememberGuestPayment(Request $request, Payment $payment): void
    {
        if ($request->user() !== null) {
            return;
        }

        $ulids = $this->guestPaymentUlids($request);

        if (! in_array($payment->ulid, $ulids, true)) {
            $ulids[] = $payment->ulid;
        }

        $request->session()->put('guest_payment_ulids', $ulids);
    }

    private function sessionOwnsGuestPayment(Request $request, Payment $payment): bool
    {
        return in_array($payment->ulid, $this->guestPaymentUlids($request), true);
    }
}
