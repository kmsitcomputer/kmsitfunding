<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Identity\PasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', ['status' => session('status')]);
    }

    /**
     * Always returns the same generic response, regardless of whether the
     * account exists (see "Privacy").
     */
    public function store(Request $request, PasswordService $passwords): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $key = 'password-reset-request:'.$request->string('email')->lower();

        if (! RateLimiter::tooManyAttempts($key, (int) config('identity.rate_limits.password_reset'))) {
            RateLimiter::hit($key, 300);
            $passwords->sendResetLink($request->string('email')->toString());
        }

        return back()->with('status', 'If an account exists for that email, password reset instructions have been sent.');
    }
}
