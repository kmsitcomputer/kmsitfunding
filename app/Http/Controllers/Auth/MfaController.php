<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Identity\AssuranceService;
use App\Services\Identity\MfaService;
use App\Services\Identity\SessionInvalidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Authenticated MFA management: enrollment, disable/reset, recovery-code
 * regeneration (Q23). Disable/reset requires fresh credential confirmation
 * AND ELEVATED assurance — enforced via the `elevated.assurance` middleware
 * plus an explicit fresh-password check here.
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

        $result = $mfa->confirmEnrollment($request->user(), $request->string('code')->toString());

        if (! $result['success']) {
            throw ValidationException::withMessages(['code' => 'That code is invalid or your enrollment has expired.']);
        }

        return redirect()->route('dashboard')->with([
            'status' => 'mfa-enrolled',
            'recoveryCodes' => $result['recovery_codes'],
        ]);
    }

    public function disable(
        Request $request,
        MfaService $mfa,
        SessionInvalidator $sessions,
        AssuranceService $assurance,
    ): RedirectResponse {
        $request->validate(['current_password' => ['required', 'string']]);

        if (! Hash::check($request->string('current_password')->toString(), $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'The provided password does not match your current password.']);
        }

        $user = $request->user();

        $mfa->disable($user);

        $sessions->invalidateAllExcept($user, $request->session()->getId());
        $request->session()->regenerate();
        $assurance->invalidate();

        return back()->with('status', 'mfa-disabled');
    }

    public function regenerateRecoveryCodes(Request $request, MfaService $mfa): RedirectResponse
    {
        $codes = $mfa->regenerateRecoveryCodes($request->user());

        return back()->with(['status' => 'mfa-recovery-codes-regenerated', 'recoveryCodes' => $codes]);
    }
}
