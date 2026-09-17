<?php

/**
 * IMP-007 — canonical per-currency minor-unit digit registry (HD-IMP007-02).
 * Sourced from the published ISO 4217 standard minor-unit table — not
 * invented business data. Deliberately non-exhaustive of all ISO 4217
 * currencies at v1; extend additively as new currencies are actually needed.
 * An unregistered currency code is rejected, never assumed to have 2 digits.
 */
return [

    'minor_units' => [
        'IDR' => 2,
        'USD' => 2,
        'EUR' => 2,
        'GBP' => 2,
        'JPY' => 0,
        'KWD' => 3,
    ],

];
