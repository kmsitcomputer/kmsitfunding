<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailChangeRequest;
use App\Notifications\ConfirmEmailChange;
use App\Services\Identity\EmailChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Canonical email-change request/verify endpoints (M01 / P2-m01). See
 * "Canonical Email Change Lifecycle" in
 * docs/implementation/IMP-002-identity-authentication.md.
 */
class EmailChangeController extends Controller
{
    public function store(Request $request, EmailChangeService $emailChange): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->string('current_password')->toString(), $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'The provided password does not match your current password.']);
        }

        $result = $emailChange->requestChange($request->user(), $request->string('email')->toString());

        // IMP002-IMPL-M02: the outward response is identical whether a request
        // was actually created or the target email was already unavailable —
        // never reveal canonical email occupancy. Only send the confirmation
        // when a request actually exists to confirm.
        if ($result['status'] === 'requested') {
            // Sent to the NEW (pending) email address only — never the old one.
            Notification::route('mail', $result['request']->normalized_pending_email)
                ->notify(new ConfirmEmailChange($result['request'], $result['plain_token']));
        }

        return back()->with('status', 'email-change-requested');
    }

    public function verify(Request $request, EmailChangeService $emailChange, EmailChangeRequest $emailChangeRequest): RedirectResponse
    {
        $token = (string) $request->query('token', '');

        $result = $emailChange->verify($request, $request->user(), $emailChangeRequest->id, $token);

        // IMP002-IMPL-M02: every non-promotion outcome (invalid token, expired,
        // cancelled, superseded, or a uniqueness conflict) returns the SAME
        // generic outward status — never disclose that the target email
        // belongs to another identity. Internal audit/request state remains
        // precise (see EmailChangeService::verify()/finalizeConflict()).
        return match ($result['status']) {
            'promoted' => redirect()->route('dashboard')->with('status', 'email-change-completed'),
            default => redirect()->route('dashboard')->with('status', 'email-change-failed'),
        };
    }
}
