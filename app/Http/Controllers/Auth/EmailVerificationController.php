<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Identity\IdentityAuditLogger;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Baseline email verification (Q21 makes email the canonical login identifier).
 * Uses Laravel's framework signed-URL verification mechanism rather than a
 * custom token table.
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/VerifyEmail', ['status' => session('status')]);
    }

    public function verify(EmailVerificationRequest $request, IdentityAuditLogger $audit): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->fulfill();

        $audit->record('email_verified', $request->user());

        return redirect()->route('dashboard');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $key = 'verification-resend:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($key, (int) config('identity.rate_limits.verification_resend'))) {
            return back()->with('status', 'verification-link-sent');
        }

        RateLimiter::hit($key, 300);

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
