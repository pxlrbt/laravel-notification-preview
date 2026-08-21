<?php

declare(strict_types=1);

return [
    /*
     * Disables discovery and route registration entirely. The viewer is always
     * disabled in the production environment, regardless of this value.
     */
    'enabled' => env('NOTIFICATION_VIEWER_ENABLED', true),

    'url_prefix' => env('NOTIFICATION_VIEWER_URL_PREFIX', 'dev/notifications'),

    'middleware' => ['web'],

    /*
     * Directories scanned recursively for concrete notification classes,
     * keyed by path with the PSR-4 root namespace of that path as value.
     */
    'paths' => [
        app_path('Notifications') => 'App\\Notifications',
    ],

    /*
     * Locales offered in the viewer. Null falls back to the directories
     * inside the application's lang path.
     */
    'locales' => null,

    /*
     * Prefills the "Send test" dialog.
     */
    'test_email' => env('NOTIFICATION_VIEWER_TEST_EMAIL'),
];
