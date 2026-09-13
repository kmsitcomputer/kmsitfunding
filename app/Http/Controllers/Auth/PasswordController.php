<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Identity\PasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Authenticated password change. Requires current-password confirmation (see
 * "Credential Model") and applies the full session/assurance invalidation
 * consequence set (see "Password Change — Session Consequences").
 */
class PasswordController extends Controller
{
    public function update(Request $request, PasswordService $passwords): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($request->string('current_password')->toString(), $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'The provided password does not match your current password.']);
        }

        $passwords->changePassword($request, $request->user(), $request->string('password')->toString());

        return back()->with('status', 'password-updated');
    }
}
