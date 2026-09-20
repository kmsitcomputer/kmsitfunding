<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Donation\Donation;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentProviderCredential;
use App\Services\Payment\Exceptions\PaymentTransitionConflictException;
use App\Services\Payment\Exceptions\PaymentValidationException;
use App\Services\Payment\PaymentCreationService;
use App\Services\Rbac\PrincipalService;
use App\Support\Money\Exceptions\UnknownCurrencyException;
use Illuminate\Http\RedirectResponse;
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
 */
class PublicPaymentController extends Controller
{
    public function store(
        StorePaymentRequest $request,
        Donation $donation,
        PaymentCreationService $service,
        PrincipalService $principals,
    ): RedirectResponse {
        abort_unless($donation->status === 'PENDING', 422);

        $idempotencyKey = $request->idempotencyKey();
        $validated = $request->validated();

        $this->assertProviderAvailable($validated['provider']);

        $user = $request->user();
        $actor = $user !== null ? $principals->forUser($user) : null;

        try {
            $payment = $service->create($donation, $validated, $actor, $idempotencyKey);
        } catch (UnknownCurrencyException $e) {
            throw ValidationException::withMessages(['currency' => $e->getMessage()]);
        } catch (PaymentValidationException $e) {
            throw ValidationException::withMessages([$e->reason => $e->getMessage()]);
        } catch (PaymentTransitionConflictException $e) {
            throw ValidationException::withMessages(['idempotency_key' => $e->getMessage()]);
        }

        return redirect()->route('public.payments.show', [
            'donation' => $donation->ulid,
            'payment' => $payment->ulid,
        ])->with('status', 'payment-created');
    }

    public function show(Donation $donation, Payment $payment)
    {
        abort_unless($payment->donation_id === $donation->id, 404);

        return response()->json(self::publicPayload($payment));
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
}
