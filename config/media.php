<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CMS Media Validation Pipeline
    |--------------------------------------------------------------------------
    |
    | docs/implementation/IMP-005-cms.md section 19. Defaults are ordinary-
    | content bounds, tunable — not a legal/policy claim.
    |
    */

    'disk' => env('CMS_MEDIA_DISK', 'public'),

    // extension => [mime, max_bytes] — the extension<->mime consistency
    // matrix (section 19 step 3). SVG is deliberately absent (section 19
    // step 7: rejected outright as an active-content vector).
    'allowed_types' => [
        'jpg' => ['mime' => 'image/jpeg', 'max_bytes' => 4 * 1024 * 1024],
        'jpeg' => ['mime' => 'image/jpeg', 'max_bytes' => 4 * 1024 * 1024],
        'png' => ['mime' => 'image/png', 'max_bytes' => 4 * 1024 * 1024],
        'gif' => ['mime' => 'image/gif', 'max_bytes' => 4 * 1024 * 1024],
        'webp' => ['mime' => 'image/webp', 'max_bytes' => 4 * 1024 * 1024],
        'pdf' => ['mime' => 'application/pdf', 'max_bytes' => 10 * 1024 * 1024],
    ],

    'image_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],

    'max_image_dimension' => (int) env('CMS_MEDIA_MAX_IMAGE_DIMENSION', 8000),

    /*
    |--------------------------------------------------------------------------
    | Cleanup (section 19 "Media cleanup model") — read by the later
    | MediaCleanupService slice; declared here now so the bounds are
    | documented in one place from the start.
    |--------------------------------------------------------------------------
    */

    'orphan_grace_hours' => (int) env('CMS_MEDIA_ORPHAN_GRACE_HOURS', 24),

    'purge_grace_days' => (int) env('CMS_MEDIA_PURGE_GRACE_DAYS', 7),

];
