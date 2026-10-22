<?php

namespace App\Http\Requests\Payment;

use App\Services\Payment\Exceptions\PaymentValidationException;
use App\Services\Payment\PaymentCreationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * IMP-009 Payment creation shape (docs/implementation/
 * IMP-009-payment-hub.md "Routes / API Boundary": POST
 * /donations/{ulid}/payments). Validates shape only; authorization is
 * a controller/policy concern and business rules belong to
 * PaymentCreationService — matching this codebase's existing
 * HTTP-boundary pattern.
 *
 * The Idempotency-Key header is REQUIRED and normalized here
 * (BR-11): a missing/malformed key is rejected before any Payment row
 * is created — via PaymentCreationService's own
 * normalizeIdempotencyKey, so the contract lives in exactly one place.
 * Ordering mirrors Donation's BR-12: preparation runs the key check
 * FIRST, inside prepareForValidation().
 */
class StorePaymentRequest extends FormRequest
{
    private ?string $resolvedIdempotencyKey = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->resolvedIdempotencyKey = $this->resolveKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:manual_transfer,tripay,xendit,stripe'],
            'channel' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function idempotencyKey(): string
    {
        return $this->resolvedIdempotencyKey ?? $this->resolveKey();
    }

    private function resolveKey(): string
    {
        try {
            return PaymentCreationService::normalizeIdempotencyKey($this->header('Idempotency-Key'));
        } catch (PaymentValidationException $e) {
            throw ValidationException::withMessages([
                'idempotency_key' => $e->getMessage(),
            ]);
        }
    }
}
