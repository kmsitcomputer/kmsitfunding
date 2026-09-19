<?php

/**
 * IMP-008 — Donation configuration (docs/implementation/IMP-008-donation.md
 * "Database Impact" / "State / Lifecycle", HD-IMP008-04, HD-IMP008-02).
 * pending_expiry_minutes is intentionally NULL by default: the expiration
 * mechanism MUST NOT invent or silently assume a duration. The sweep does
 * nothing while this value is unset. The frequency allow-list holds exactly
 * the v1-authorized values (MONTHLY); additional values require an
 * authorized extension, never a unilateral addition here.
 */
return [

    'pending_expiry_minutes' => env('DONATION_PENDING_EXPIRY_MINUTES'),

    'recurring_frequencies' => ['MONTHLY'],

];
