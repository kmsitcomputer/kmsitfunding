<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Assurance
    |--------------------------------------------------------------------------
    |
    | Finite lifetime (minutes) of an ELEVATED authentication assurance state
    | before it automatically falls back to STANDARD. Not fixed by any
    | materialized document — a secure, configurable default.
    |
    */

    'elevated_assurance_ttl_minutes' => (int) env('IDENTITY_ELEVATED_TTL_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Email Change Requests
    |--------------------------------------------------------------------------
    */

    'email_change_request_ttl_minutes' => (int) env('IDENTITY_EMAIL_CHANGE_TTL_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    */

    'invitation_ttl_days' => (int) env('IDENTITY_INVITATION_TTL_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | MFA
    |--------------------------------------------------------------------------
    */

    'mfa_pending_enrollment_ttl_minutes' => (int) env('IDENTITY_MFA_ENROLLMENT_TTL_MINUTES', 15),
    'mfa_recovery_code_count' => (int) env('IDENTITY_MFA_RECOVERY_CODE_COUNT', 8),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Secure, configurable defaults — not fixed by any materialized document.
    | "Configurable" never means "unrestricted".
    |
    */

    'rate_limits' => [
        'login' => (int) env('IDENTITY_RATE_LIMIT_LOGIN', 10),
        'registration' => (int) env('IDENTITY_RATE_LIMIT_REGISTRATION', 5),
        'password_reset' => (int) env('IDENTITY_RATE_LIMIT_PASSWORD_RESET', 5),
        'verification_resend' => (int) env('IDENTITY_RATE_LIMIT_VERIFICATION_RESEND', 5),
        'mfa_challenge' => (int) env('IDENTITY_RATE_LIMIT_MFA_CHALLENGE', 5),
        'mfa_enrollment_confirm' => (int) env('IDENTITY_RATE_LIMIT_MFA_ENROLLMENT_CONFIRM', 10),
        'mfa_elevate' => (int) env('IDENTITY_RATE_LIMIT_MFA_ELEVATE', 5),
        'mfa_disable' => (int) env('IDENTITY_RATE_LIMIT_MFA_DISABLE', 5),
        'mfa_reset' => (int) env('IDENTITY_RATE_LIMIT_MFA_RESET', 5),
        'invitation_acceptance' => (int) env('IDENTITY_RATE_LIMIT_INVITATION_ACCEPTANCE', 10),
    ],

];
