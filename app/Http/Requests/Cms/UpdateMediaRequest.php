<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * IMP-005 admin UI (slice 23). Only the three mutable columns MediaService::
 * updateMetadata() will accept (section 13): alt_text, caption,
 * original_filename.
 */
class UpdateMediaRequest extends FormRequest
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
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
            'original_filename' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
