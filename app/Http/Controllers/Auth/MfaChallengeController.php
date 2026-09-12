<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Identity\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Second-factor TOTP/recovery-code challenge during login, for identities
 * with MFA enabled (Q23).
 */
class MfaChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('identity.pending_mfa_user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/MfaChallenge');
    }

    public function store(Request $request, AuthenticationService $auth): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'recovery' => ['sometimes', 'boolean'],
        ]);

        $key = 'mfa-challenge:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) config('identity.rate_limits.mfa_challenge'))) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Please try again later.']);
        }

        $result = $auth->attemptMfaChallenge(
            $request,
            $request->string('code')->toString(),
            (bool) $request->boolean('recovery'),
        );

        if ($result['status'] !== 'authenticated') {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['code' => 'That code is invalid.']);
        }

        RateLimiter::clear($key);

        return redirect()->intended(route('dashboard'));
    }
}
