<?php

/**
 * IMP-007 — Campaign/Program media + default currency configuration
 * (docs/implementation/IMP-007-campaign-program-fund.md sections 8a/13).
 * Mirrors config/theme.php's shape: tighter allow-list than CMS media,
 * since campaign/program imagery is presentation, not document-attachment.
 */
return [

    'disk' => env('CAMPAIGN_MEDIA_DISK', 'public'),

    'default_currency' => env('CAMPAIGN_DEFAULT_CURRENCY', 'IDR'),

    'allowed_types' => [
        'jpg' => ['mime' => 'image/jpeg', 'max_bytes' => 5 * 1024 * 1024],
        'jpeg' => ['mime' => 'image/jpeg', 'max_bytes' => 5 * 1024 * 1024],
        'png' => ['mime' => 'image/png', 'max_bytes' => 5 * 1024 * 1024],
        'webp' => ['mime' => 'image/webp', 'max_bytes' => 5 * 1024 * 1024],
    ],

    'max_image_dimension' => env('CAMPAIGN_MEDIA_MAX_IMAGE_DIMENSION', 4000),

];
