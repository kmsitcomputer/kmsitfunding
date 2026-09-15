<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * IMP-005 admin UI (slice 24). `page_ulid` null clears the designation
 * (PublicationService::setHomepage($page = null, ...)).
 * `expected_page_ulid` mirrors the service's optimistic
 * $expectedPageId guard (section 26 flow 7).
 */
class SetHomepageRequest extends FormRequest
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
            'page_ulid' => ['nullable', 'string', 'exists:cms_pages,ulid'],
            'expected_page_ulid' => ['nullable', 'string'],
        ];
    }
}
