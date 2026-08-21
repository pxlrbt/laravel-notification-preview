<?php

declare(strict_types=1);

return [
    /*
     * Disables discovery and route registration entirely. The preview is always
     * disabled in the production environment, regardless of this value.
     */
    'enabled' => env('NOTIFICATION_PREVIEW_ENABLED', true),

    /*
     * The kinds of classes the preview looks for. Turn either off to keep it out
     * of discovery completely.
     */
    'notifications' => env('NOTIFICATION_PREVIEW_NOTIFICATIONS', true),
    'mailables' => env('NOTIFICATION_PREVIEW_MAILABLES', true),

    /*
     * The channels the preview can show. Mail is rendered as HTML and text;
     * every other channel is dumped as the JSON payload it hands its provider.
     * Names match both driver strings and channel classes, so 'smsapi' also
     * covers an SmsapiChannel::class in via().
     */
    'channels' => ['mail'],

    'url_prefix' => env('NOTIFICATION_PREVIEW_URL_PREFIX', 'dev/notifications'),

    'middleware' => ['web'],

    /*
     * Everybody outside the production environment may open the preview, where
     * its routes are never registered in the first place. Narrow that down with
     * NotificationPreview::auth() in a service provider:
     *
     *   NotificationPreview::auth(fn () => app()->isLocal());
     *   NotificationPreview::auth(fn (?Request $request) => $request?->user()?->isAdmin());
     */

    /*
     * Directories scanned recursively. The namespace of each directory is read
     * from Composer's PSR-4 map, so directories outside it are skipped.
     */
    'paths' => [
        app_path('Notifications'),
        app_path('Mail'),
    ],

    /*
     * Classes to hide from the preview. Accepts fully qualified class names and
     * namespaces; a namespace hides everything below it.
     *
     *   App\Notifications\Internal\DebugPing::class
     *   'App\Notifications\Internal'
     */
    'exclude' => [],

    /*
     * Locales offered in the preview. Null falls back to the directories
     * inside the application's lang path.
     */
    'locales' => null,

    /*
     * Prefills the "Send test" dialog.
     */
    'test_email' => env('NOTIFICATION_PREVIEW_TEST_EMAIL'),
];
