<?php

namespace App\Http\Requests\Donation;

use App\Services\Donation\DonationService;
use App\Services\Donation\Exceptions\DonationValidationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * IMP-008 public/guest-or-authenticated Donation creation shape
 * (docs/implementation/IMP-008-donation.md "API Impact" — POST
 * /campaigns/{slug}/donations). Validates shape only; authorization is a
 * controller/policy concern and business rules belong to DonationService —
 * matching this codebase's existing HTTP-boundary pattern.
 *
 * The Idempotency-Key header is REQUIRED and normalized here (BR-12):
 * a missing/malformed key is rejected before any Donation row is created
 * or any Campaign-eligibility check runs — via DonationService's own
 * normalizeIdempotencyKey, so the contract lives in exactly one place.
 *
 * Ordering (BR-12 exact): preparation runs the key check FIRST, inside
 * prepareForValidation() — which Laravel invokes before the payload
 * rules below are even evaluated — and the controller resolves the key
 * again before its eligibility gate. The service re-validates as
 * defense in depth.
 */
class StoreDonationRequest extends FormRequest
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
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'is_anonymous' => ['sometimes', 'boolean'],
            'donor_display_name' => ['nullable', 'string', 'max:150'],
            'guest_name' => ['nullable', 'string', 'max:150'],
            'guest_email' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function idempotencyKey(): string
    {
        return $this->resolvedIdempotencyKey ?? $this->resolveKey();
    }

    private function resolveKey(): string
    {
        try {
            return DonationService::normalizeIdempotencyKey($this->header('Idempotency-Key'));
        } catch (DonationValidationException $e) {
            throw ValidationException::withMessages([
                'idempotency_key' => $e->getMessage(),
            ]);
        }
    }
}
