<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Identity\PasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->query('email', ''),
            'token' => $request->route('token'),
        ]);
    }

    public function store(Request $request, PasswordService $passwords): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $succeeded = $passwords->completeReset(
            $request,
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        if (! $succeeded) {
            // Generic — never confirms whether the underlying account exists,
            // nor whether the token was merely expired vs. invalid.
            throw ValidationException::withMessages([
                'email' => 'This password reset link is invalid or has expired.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset.');
    }
}
