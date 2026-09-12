<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Identity\AssuranceService;
use App\Services\Identity\MfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Authenticated MFA management: enrollment, ELEVATED step-up, disable/reset,
 * recovery-code regeneration (Q23). Disable/reset require fresh credential
 * confirmation AND ELEVATED assurance (enforced via the `elevated.assurance`
 * middleware) AND a fresh, replay-guarded proof of the active factor itself
 * (TOTP code or recovery code) — see MfaService::disable()/reset().
 */
class MfaController extends Controller
{
    public function enroll(Request $request, MfaService $mfa): Response
    {
        $data = $mfa->startEnrollment($request->user());

        return Inertia::render('Auth/MfaEnroll', $data);
    }

    public function confirm(Request $request, MfaService $mfa): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        // IMP002-IMPL-M03: an authenticated identity must not be able to
        // brute-force TOTP enrollment confirmation without throttle.
        $key = 'mfa-enrollment-confirm:'.$request->user()->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) config('identity.rate_limits.mfa_enrollment_confirm'))) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Please try again later.']);
        }

        $result = $mfa->confirmEnrollment($request->user(), $request->string('code')->toString());

        if (! $result['success']) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['code' => 'That code is invalid or your enrollment has expired.']);
        }

        RateLimiter::clear($key);

        return redirect()->route('dashboard')->with([
            'status' => 'mfa-enrolled',
            'recoveryCodes' => $result['recovery_codes'],
        ]);
    }

    /**
     * ELEVATED step-up via a fresh TOTP challenge — the second of the two
     * mechanisms ("Authentication Assurance") that can satisfy ELEVATED,
     * alongside fresh password confirmation (ConfirmablePasswordController).
     * Replay-guarded under its own context ('elevate'), distinct from the
     * login-time challenge.
     */
    public function elevate(Request $request, MfaService $mfa, AssuranceService $assurance): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        // IMP002-REAUDIT-M01: every production TOTP-verification path must be
        // throttled, not only enrollment/login. Keyed by flow + authenticated
        // User id + IP — never by email, and never shared with an unrelated
        // MFA flow's bucket.
        $key = 'mfa-elevate:'.$request->user()->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) config('identity.rate_limits.mfa_elevate'))) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Please try again later.']);
        }

        if (! $mfa->verifyChallenge($request->user(), 'elevate', $request->string('code')->toString())) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['code' => 'That code is invalid.']);
        }

        RateLimiter::clear($key);

        $assurance->elevate();

        return redirect()->intended(route('dashboard'));
    }

    public function disable(Request $request, MfaService $mfa): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        if (! Hash::check($request->string('current_password')->toString(), $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'The provided password does not match your current password.']);
        }

        // IMP002-REAUDIT-M01: throttle the TOTP/recovery-code proof itself —
        // fresh password confirmation alone does not protect the codespace
        // against a brute-forced 'code'/'recovery_code' value.
        $key = 'mfa-disable:'.$request->user()->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) config('identity.rate_limits.mfa_disable'))) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Please try again later.']);
        }

        $disabled = $mfa->disable(
            $request,
            $request->user(),
            $request->string('code')->toString() ?: null,
            $request->string('recovery_code')->toString() ?: null,
        );

        if (! $disabled) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['code' => 'A current authentication code or recovery code is required to disable MFA.']);
        }

        RateLimiter::clear($key);

        return back()->with('status', 'mfa-disabled');
    }

    /**
     * Self-service reset: same effect as disable(), but intended for an
     * identity that still holds its device and wants to rotate its factor in
     * one step (invalidate current secret, then immediately re-enroll via
     * enroll()), rather than a lost-device/administrative recovery scenario.
     */
    public function reset(Request $request, MfaService $mfa): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        if (! Hash::check($request->string('current_password')->toString(), $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'The provided password does not match your current password.']);
        }

        $key = 'mfa-reset:'.$request->user()->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) config('identity.rate_limits.mfa_reset'))) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Please try again later.']);
        }

        $reset = $mfa->reset(
            $request,
            $request->user(),
            $request->string('code')->toString() ?: null,
            $request->string('recovery_code')->toString() ?: null,
        );

        if (! $reset) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['code' => 'A current authentication code or recovery code is required to reset MFA.']);
        }

        RateLimiter::clear($key);

        return back()->with('status', 'mfa-reset');
    }

    public function regenerateRecoveryCodes(Request $request, MfaService $mfa): RedirectResponse
    {
        $codes = $mfa->regenerateRecoveryCodes($request->user());

        return back()->with(['status' => 'mfa-recovery-codes-regenerated', 'recoveryCodes' => $codes]);
    }
}
