<?php

declare(strict_types=1);

return [
    /*
     * Disables discovery and route registration entirely. The viewer is always
     * disabled in the production environment, regardless of this value.
     */
    'enabled' => env('NOTIFICATION_VIEWER_ENABLED', true),

    /*
     * The kinds of classes the viewer looks for. Turn either off to keep it out
     * of discovery completely.
     */
    'notifications' => env('NOTIFICATION_VIEWER_NOTIFICATIONS', true),
    'mailables' => env('NOTIFICATION_VIEWER_MAILABLES', true),

    'url_prefix' => env('NOTIFICATION_VIEWER_URL_PREFIX', 'dev/notifications'),

    'middleware' => ['web'],

    /*
     * Directories scanned recursively, keyed by path with the PSR-4 root
     * namespace of that path as value.
     */
    'paths' => [
        app_path('Notifications') => 'App\\Notifications',
        app_path('Mail') => 'App\\Mail',
    ],

    /*
     * Classes to hide from the viewer. Accepts fully qualified class names and
     * namespaces; a namespace hides everything below it.
     *
     *   App\Notifications\Internal\DebugPing::class
     *   'App\Notifications\Internal'
     */
    'exclude' => [],

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
