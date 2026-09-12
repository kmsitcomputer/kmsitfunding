<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Identity\EmailNormalizer;
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
    public function store(Request $request, PasswordService $passwords, EmailNormalizer $normalizer): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // IMP002-IMPL-M06: use the SAME canonical normalizer as registration/
        // login/invitation/email-change/bootstrap — not an ad hoc lowercase —
        // so " User@Example.com " keys the rate limiter identically to its
        // canonical stored form and is looked up correctly by the broker.
        $normalized = $normalizer->normalize($request->string('email')->toString());
        $key = 'password-reset-request:'.$normalized;

        if (! RateLimiter::tooManyAttempts($key, (int) config('identity.rate_limits.password_reset'))) {
            RateLimiter::hit($key, 300);
            $passwords->sendResetLink($normalized);
        }

        return back()->with('status', 'If an account exists for that email, password reset instructions have been sent.');
    }
}
