<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Rbac\Principal;
use App\Policies\PaymentPolicy;
use App\Services\Payment\Exceptions\PaymentTransitionConflictException;
use App\Services\Payment\Exceptions\PaymentValidationException;
use App\Services\Payment\ManualTransferVerificationService;
use App\Services\Payment\PaymentTransitionService;
use App\Services\Rbac\PrincipalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IMP-009 admin/backoffice Payment views and manual-transfer
 * verification (docs/implementation/IMP-009-payment-hub.md "Routes /
 * API Boundary": GET /admin/payment/payments, GET
 * /admin/payment/payments/{ulid}, POST
 * /admin/payment/payments/{ulid}/manual-transfer/{approve,reject,hold},
 * POST /admin/payment/payments/{ulid}/cancel — the authorized
 * support-controlled cancellation surface HD-IMP009-04 permits for
 * freeing an abandoned attempt's ACTIVE slot).
 * ORGANIZATION scope throughout, mirroring the admin/donation/*
 * convention. Approve/reject/hold additionally require
 * financial_approver Business Authority (PaymentPolicy) — the
 * AMOUNT_MISMATCH_HOLD outcome uses the identical gate.
 */
class AdminPaymentController extends Controller
{
    public function index(Request $request, PaymentPolicy $policy): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewAny($actor), 403);

        $payments = Payment::query()
            ->latest('id')
            ->paginate(20)
            ->through(fn (Payment $payment) => $this->adminPayload($payment));

        return Inertia::render('Admin/Payment/Index', [
            'payments' => $payments,
        ]);
    }

    public function show(Request $request, PaymentPolicy $policy, Payment $payment): Response
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->viewPayment($actor, $payment), 403);

        return Inertia::render('Admin/Payment/Show', [
            'payment' => $this->adminPayload($payment),
        ]);
    }

    public function approve(Request $request, PaymentPolicy $policy, Payment $payment, ManualTransferVerificationService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->verifyManualTransfer($actor, $payment), 403);

        $validated = $request->validate([
            'evidence_id' => ['required', 'integer'],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->approve($payment, $actor, (int) $validated['evidence_id'], $validated['review_notes'] ?? null);
        } catch (PaymentValidationException|PaymentTransitionConflictException $e) {
            throw ValidationException::withMessages(['payment' => $e->getMessage()]);
        }

        return redirect()->route('payment.admin.show', $payment)->with('status', 'manual-transfer-approved');
    }

    public function reject(Request $request, PaymentPolicy $policy, Payment $payment, ManualTransferVerificationService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->verifyManualTransfer($actor, $payment), 403);

        $validated = $request->validate([
            'evidence_id' => ['nullable', 'integer'],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->reject(
                $payment,
                $actor,
                isset($validated['evidence_id']) ? (int) $validated['evidence_id'] : null,
                $validated['review_notes'] ?? null
            );
        } catch (PaymentValidationException|PaymentTransitionConflictException $e) {
            throw ValidationException::withMessages(['payment' => $e->getMessage()]);
        }

        return redirect()->route('payment.admin.show', $payment)->with('status', 'manual-transfer-rejected');
    }

    public function hold(Request $request, PaymentPolicy $policy, Payment $payment, ManualTransferVerificationService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->verifyManualTransfer($actor, $payment), 403);

        $validated = $request->validate([
            'evidence_id' => ['required', 'integer'],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->holdForAmountMismatch($payment, $actor, (int) $validated['evidence_id'], $validated['review_notes'] ?? null);
        } catch (PaymentValidationException|PaymentTransitionConflictException $e) {
            throw ValidationException::withMessages(['payment' => $e->getMessage()]);
        }

        return redirect()->route('payment.admin.show', $payment)->with('status', 'manual-transfer-held');
    }

    public function cancel(Request $request, PaymentPolicy $policy, Payment $payment, PaymentTransitionService $service): RedirectResponse
    {
        $actor = $this->resolveActingPrincipal($request);
        abort_unless($policy->cancelAny($actor, $payment), 403);

        try {
            $service->cancel($payment, $actor);
        } catch (PaymentTransitionConflictException $e) {
            throw ValidationException::withMessages(['payment' => $e->getMessage()]);
        }

        return redirect()->route('payment.admin.show', $payment)->with('status', 'payment-cancelled');
    }

    /**
     * @return array<string, mixed>
     */
    private function adminPayload(Payment $payment): array
    {
        return [
            'ulid' => $payment->ulid,
            'status' => $payment->status,
            'provider' => $payment->provider,
            'provider_reference' => $payment->provider_reference,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'created_at' => $payment->created_at,
        ];
    }

    private function resolveActingPrincipal(Request $request): Principal
    {
        return app(PrincipalService::class)->forUser($request->user());
    }
}
