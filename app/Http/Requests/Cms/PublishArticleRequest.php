<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * IMP-005 admin UI (slice 22). Mirrors PublishPageRequest.
 */
class PublishArticleRequest extends FormRequest
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
