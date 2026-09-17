<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Path Namespace
    |--------------------------------------------------------------------------
    |
    | docs/implementation/IMP-005-cms.md section 14. The 191-character TOTAL
    | path bound is NOT configurable here — it maps 1:1 onto the cms_paths.path
    | VARCHAR(191) column and is asserted as a literal constant in PathService.
    |
    */

    'path' => [

        'max_segment_length' => (int) env('CMS_PATH_MAX_SEGMENT_LENGTH', 100),

        'max_depth' => (int) env('CMS_PATH_MAX_DEPTH', 3),

        // MASTER-REQUIREMENTS §3 protected prefixes — reserved at every depth,
        // not merely as literal first segments, because later stages will
        // register routes under them not yet in the live route table.
        'protected_prefixes' => [
            'admin', 'api', 'donor', 'fundraiser', 'partner', 'campaign',
            'zakat', 'wakaf', 'fidyah', 'qurban',
        ],

        // Framework/ops paths that are not Laravel routes but are owned by
        // the deploy.
        'framework_paths' => [
            'storage', 'up', '_ignition', '_debugbar', 'build', 'vendor',
            'favicon.ico', 'robots.txt', 'sitemap.xml', 'manifest.webmanifest',
            '.well-known',
        ],

        // An explicit deny-overrides list for routes that must stay
        // unclaimable even if unregistered later. Empty in v1 — no retired
        // route to protect yet.
        'deny_overrides' => [],

    ],

];
