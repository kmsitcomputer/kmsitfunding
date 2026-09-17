<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * IMP-005 admin UI (slice 21). Authorization is a controller-level concern
 * (ContentPagePolicy), matching this codebase's existing HTTP-boundary
 * pattern — this request only validates shape.
 */
class StorePageRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:204800'],
            'excerpt' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string'],
            'no_index' => ['sometimes', 'boolean'],
        ];
    }
}
