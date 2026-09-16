<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IMP-006 Theme Asset Validation Pipeline
    |--------------------------------------------------------------------------
    |
    | docs/implementation/IMP-006-theme-engine.md section 17. Mirrors
    | config/media.php's own discipline exactly, scoped to visual theme
    | assets only (logos/favicons/decorative images) — never PDFs or other
    | content-document types.
    |
    */

    'disk' => env('THEME_ASSET_DISK', 'public'),

    'allowed_types' => [
        'jpg' => ['mime' => 'image/jpeg', 'max_bytes' => 2 * 1024 * 1024],
        'jpeg' => ['mime' => 'image/jpeg', 'max_bytes' => 2 * 1024 * 1024],
        'png' => ['mime' => 'image/png', 'max_bytes' => 2 * 1024 * 1024],
        'webp' => ['mime' => 'image/webp', 'max_bytes' => 2 * 1024 * 1024],
    ],

    'max_image_dimension' => (int) env('THEME_ASSET_MAX_IMAGE_DIMENSION', 4000),

];
