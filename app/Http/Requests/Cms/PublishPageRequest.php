<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * IMP-005 admin UI (slice 21). `path` is omitted for a same-path replacement
 * / re-publication — required only for first publication or a rename,
 * which PublicationService::publish() itself enforces.
 */
class PublishPageRequest extends FormRequest
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
            'path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
