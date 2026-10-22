<?php

/**
 * IMP-009 — Payment Hub configuration (docs/implementation/
 * IMP-009-payment-hub.md "Expiration" / "Configuration", HD-IMP009-09,
 * FINAL / LOCKED). pending_expiry_minutes is intentionally NULL by
 * default: the internal fallback expiry MUST NOT act while unset — no
 * numeric default is asserted (inventing one is prohibited), mirroring
 * HD-IMP008-04's identical "no invented default" contract. Manual
 * Transfer has no provider-supplied expiry, so while unset an
 * unreviewed Manual Transfer Payment remains PENDING indefinitely.
 */
return [

    'pending_expiry_minutes' => env('PAYMENT_PENDING_EXPIRY_MINUTES'),

    'evidence_max_kilobytes' => env('PAYMENT_EVIDENCE_MAX_KILOBYTES', 5120),

    'evidence_allowed_mimes' => ['image/jpeg', 'image/png', 'application/pdf'],

];
