<?php

namespace App\Services\Identity;

use Illuminate\Support\Facades\Session;

/**
 * STANDARD / ELEVATED Authentication Assurance (docs/05-rbac/AUTHENTICATION-ASSURANCE.md,
 * "Authentication Assurance" in the IMP-002 specification).
 *
 * Session-scoped security metadata — deliberately NOT a User column. A new
 * session never inherits a prior session's ELEVATED assurance.
 */
class AssuranceService
{
    private const SESSION_KEY = 'identity.assurance';

    public function isElevated(): bool
    {
        $until = Session::get(self::SESSION_KEY.'.elevated_until');

        return $until !== null && now()->lessThan($until);
    }

    public function elevate(): void
    {
        Session::put(self::SESSION_KEY.'.elevated_at', now());
        Session::put(
            self::SESSION_KEY.'.elevated_until',
            now()->addMinutes((int) config('identity.elevated_assurance_ttl_minutes'))
        );
    }

    /**
     * Invalidate ELEVATED immediately. Required on: logout, password change,
     * email-change completion, MFA reset/disable/re-enrollment, SUSPENDED/DISABLED
     * transition, and any other session invalidation (see "Authentication
     * Assurance > Immediate Invalidation Triggers").
     */
    public function invalidate(): void
    {
        Session::forget([
            self::SESSION_KEY.'.elevated_at',
            self::SESSION_KEY.'.elevated_until',
        ]);
    }

    public function level(): string
    {
        return $this->isElevated() ? 'ELEVATED' : 'STANDARD';
    }
}
