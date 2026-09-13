<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\Identity\AuthenticationService;
use App\Services\Identity\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Invitation acceptance for Partner Representative / Internal Administrative
 * Identity / Super Admin (Q22, M02). Acceptance creates an Identity/Auth
 * subject ONLY — no role/permission/business authority is granted here.
 */
class InvitationController extends Controller
{
    public function accept(Request $request, Invitation $invitation): Response
    {
        return Inertia::render('Auth/AcceptInvitation', [
            'invitation' => $invitation->public_id,
        ]);
    }

    public function store(
        Request $request,
        Invitation $invitation,
        InvitationService $invitations,
        AuthenticationService $auth,
    ): RedirectResponse {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $key = 'invitation-acceptance:'.$invitation->public_id;

        if (RateLimiter::tooManyAttempts($key, (int) config('identity.rate_limits.invitation_acceptance'))) {
            throw ValidationException::withMessages(['token' => 'Too many attempts. Please try again later.']);
        }

        RateLimiter::hit($key, 60);

        $result = $invitations->accept(
            $invitation,
            $request->string('token')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        if ($result['status'] !== 'accepted') {
            throw ValidationException::withMessages(['token' => 'This invitation is invalid, expired, or has already been used.']);
        }

        RateLimiter::clear($key);

        $auth->attemptPassword($request, $request->string('email')->toString(), $request->string('password')->toString());

        return redirect()->route('dashboard');
    }
}
