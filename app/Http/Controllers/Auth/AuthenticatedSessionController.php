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

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'mfaPending' => session()->has('identity.pending_mfa_user_id'),
        ]);
    }

    public function store(Request $request, AuthenticationService $auth): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.$request->string('email')->lower().'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) config('identity.rate_limits.login'))) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again later.',
            ]);
        }

        $result = $auth->attemptPassword($request, $request->string('email')->toString(), $request->string('password')->toString());

        if ($result['status'] === 'failed') {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);

        if ($result['status'] === 'mfa_required') {
            return redirect()->route('mfa.challenge.create');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AuthenticationService $auth): RedirectResponse
    {
        $auth->logout($request);

        return redirect()->route('login');
    }
}
