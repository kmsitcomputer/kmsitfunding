<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Services\Identity\AuthenticationService;
use App\Services\Identity\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    ): RedirectResponse {
        $registration->register($request->string('email')->toString(), $request->string('password')->toString());

        $auth->attemptPassword($request, $request->string('email')->toString(), $request->string('password')->toString());

        return redirect()->route('dashboard');
    }
}
