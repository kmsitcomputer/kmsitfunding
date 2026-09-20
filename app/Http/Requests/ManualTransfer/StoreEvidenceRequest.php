<?php

namespace App\Http\Requests\ManualTransfer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * IMP-009 Manual Transfer evidence-upload shape
 * (docs/implementation/IMP-009-payment-hub.md "Manual Transfer" /
 * "File Security", HD-IMP009-06). Validates request shape only —
 * server-side MIME/size enforcement lives in
 * ManualTransferEvidenceService (never trusting the client
 * Content-Type alone); authorization is a controller/policy concern.
 */
class StoreEvidenceRequest extends FormRequest
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
            'evidence' => ['required', 'file', 'max:5120'],
            'declared_amount_minor' => ['nullable', 'integer', 'min:1'],
            'declared_currency' => ['nullable', 'string', 'size:3'],
            'declared_transferred_at' => ['nullable', 'date'],
        ];
    }
}
