<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Services\Identity\AuthenticationService;
use App\Services\Identity\EmailNormalizer;
use App\Services\Identity\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Donor and Fundraiser self-registration (Q22). Both actors use the exact
 * same mechanics — only the route/portal context differs; neither grants any
 * business authority.
 */
class RegisteredUserController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'actor' => $request->routeIs('fundraiser.register') ? 'fundraiser' : 'donor',
        ]);
    }

    public function store(
        RegisterUserRequest $request,
        RegistrationService $registration,
        AuthenticationService $auth,
        EmailNormalizer $normalizer,
    ): RedirectResponse {
        $key = 'registration:'.$normalizer->normalize($request->string('email')->toString()).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) config('identity.rate_limits.registration'))) {
            throw ValidationException::withMessages([
                'email' => 'Too many registration attempts. Please try again later.',
            ]);
        }

        RateLimiter::hit($key, 60);

        $registration->register($request->string('email')->toString(), $request->string('password')->toString());

        $auth->attemptPassword($request, $request->string('email')->toString(), $request->string('password')->toString());

        RateLimiter::clear($key);

        return redirect()->route('dashboard');
    }
}
