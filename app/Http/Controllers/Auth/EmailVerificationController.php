<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Identity\IdentityAuditLogger;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // IMP-004 (IMP004-SPEC-M03): identity.email.verified is
        // CRITICAL/MUTATION_ATOMIC — the verification state change and the
        // canonical audit append are ONE atomic transaction; a forced audit
        // failure leaves the email unverified with no orphan audit record.
        DB::transaction(function () use ($request, $audit) {
            $request->fulfill();

            $audit->record('email_verified', $request->user());
        });

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
