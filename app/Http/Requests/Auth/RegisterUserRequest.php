<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Identity\EmailNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $normalizer = app(EmailNormalizer::class);

        return [
            'email' => [
                'required', 'string', 'email', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($normalizer) {
                    if (User::where('email', $normalizer->normalize((string) $value))->exists()) {
                        $fail('This email is already in use.');
                    }
                },
            ],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
