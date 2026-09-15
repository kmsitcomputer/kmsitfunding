<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * IMP-005 admin UI (slice 22). Mirrors UpdatePageRequest — see its doc
 * comment — plus optional `article_type`.
 */
class UpdateArticleRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'body_html' => ['sometimes', 'string', 'max:204800'],
            'article_type' => ['sometimes', 'string', 'in:ARTICLE,NEWS'],
            'excerpt' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string'],
            'no_index' => ['sometimes', 'boolean'],
            'expected_edit_version' => ['required', 'integer', 'min:0'],
        ];
    }
}
