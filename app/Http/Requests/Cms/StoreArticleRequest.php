<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * IMP-005 admin UI (slice 22). Mirrors StorePageRequest — see its doc
 * comment — plus `article_type` (ARTICLE|NEWS, defaulting ARTICLE;
 * RevisionService itself validates/defaults this, Q33/HD-IMP005-05: News
 * is Article classification, never a separate entity).
 */
class StoreArticleRequest extends FormRequest
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
            'article_type' => ['nullable', 'string', 'in:ARTICLE,NEWS'],
            'excerpt' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string'],
            'no_index' => ['sometimes', 'boolean'],
        ];
    }
}
