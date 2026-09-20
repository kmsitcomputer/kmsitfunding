<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManualTransfer\StoreEvidenceRequest;
use App\Models\Donation\Donation;
use App\Models\Payment\ManualTransferEvidence;
use App\Models\Payment\Payment;
use App\Models\Rbac\Principal;
use App\Policies\PaymentPolicy;
use App\Services\Payment\Exceptions\PaymentTransitionConflictException;
use App\Services\Payment\Exceptions\PaymentValidationException;
use App\Services\Payment\ManualTransferEvidenceService;
use App\Services\Payment\PaymentTransitionService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * IMP-009 donor-owned Payment views/actions (docs/implementation/
 * IMP-009-payment-hub.md "Routes / API Boundary": GET
 * /me/donations/{ulid}/payments, GET
 * /me/donations/{ulid}/payments/{payment_ulid}, POST
 * /me/donations/{ulid}/payments/{payment_ulid}/cancel, POST
 * .../manual-transfer/evidence). OWN scope throughout — a donor
 * sees/cancels/submits only for their own Payments. Guest has no
 * listing path (no account — HD-IMP009-04, mirroring HD-IMP008-05B).
 * Evidence file retrieval is ownership-and-permission-gated through
 * this application route — never a permanent public URL.
 */
class DashboardPaymentController extends Controller
{
    public function index(Request $request, PaymentPolicy $policy, Donation $donation): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewOwnList($actor), 403);
        abort_unless($this->ownsDonation($actor, $donation), 403);

        $payments = Payment::query()
            ->where('donation_id', $donation->id)
            ->latest('id')
            ->paginate(20)
            ->through(fn (Payment $payment) => $this->ownPayload($payment));

        return Inertia::render('Payment/Index', [
            'donation_ulid' => $donation->ulid,
            'payments' => $payments,
        ]);
    }

    public function show(Request $request, PaymentPolicy $policy, Donation $donation, Payment $payment): Response
    {
        abort_unless($payment->donation_id === $donation->id, 404);

        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewOwn($actor, $payment), 403);

        return Inertia::render('Payment/Show', [
            'payment' => $this->ownPayload($payment),
        ]);
    }

    public function cancel(Request $request, PaymentPolicy $policy, Donation $donation, Payment $payment, PaymentTransitionService $service): RedirectResponse
    {
        abort_unless($payment->donation_id === $donation->id, 404);

        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->cancelOwn($actor, $payment), 403);

        try {
            $service->cancel($payment, $actor);
        } catch (PaymentTransitionConflictException $e) {
            throw ValidationException::withMessages(['payment' => $e->getMessage()]);
        }

        return redirect()->route('payments.show', [$donation, $payment])->with('status', 'payment-cancelled');
    }

    public function storeEvidence(
        StoreEvidenceRequest $request,
        PaymentPolicy $policy,
        Donation $donation,
        Payment $payment,
        ManualTransferEvidenceService $service,
    ): RedirectResponse {
        abort_unless($payment->donation_id === $donation->id, 404);

        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->submitEvidence($actor, $payment), 403);

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

        return redirect()->route('payments.show', [$donation, $payment])->with('status', 'evidence-submitted');
    }

    public function showEvidence(Request $request, PaymentPolicy $policy, Donation $donation, Payment $payment, ManualTransferEvidence $evidence): StreamedResponse
    {
        abort_unless($payment->donation_id === $donation->id, 404);
        abort_unless($evidence->payment_id === $payment->id, 404);

        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewOwn($actor, $payment), 403);

        abort_unless(Storage::disk('local')->exists($evidence->file_path), 404);

        return Storage::disk('local')->response($evidence->file_path, headers: [
            'Content-Type' => $evidence->mime_type,
            'Content-Disposition' => 'inline',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ownPayload(Payment $payment): array
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

    private function ownsDonation(Principal $actor, Donation $donation): bool
    {
        return $donation->donor_principal_id !== null && $donation->donor_principal_id === $actor->id;
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }
}
