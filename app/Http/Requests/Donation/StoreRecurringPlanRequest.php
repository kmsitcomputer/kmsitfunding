<?php

namespace App\Http\Requests\Donation;

use Illuminate\Foundation\Http\FormRequest;

/**
 * IMP-008 Recurring Plan creation shape (authenticated-donor-only —
 * guest recurring is NOT supported, HD-IMP008-01B/BR-6; the controller
 * rejects unauthenticated callers before reaching the service).
 * Frequency accepts exactly the configured allow-list values (v1:
 * MONTHLY only, BR-9); the service re-validates authoritatively.
 */
class StoreRecurringPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'campaign_ulid' => ['required', 'string', 'size:26'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'frequency' => ['required', 'string', 'max:16'],
            'is_anonymous' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
