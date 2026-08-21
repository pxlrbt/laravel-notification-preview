<?php

declare(strict_types=1);

use pxlrbt\LaravelNotificationViewer\Facades\NotificationViewer;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\AbstractNotification;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\Nested\DeepNotification;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\NotANotification;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\ScalarNotification;

it('discovers concrete notifications in the configured paths', function () {
    expect(NotificationViewer::classes()->all())->toContain(ScalarNotification::class);
});

it('discovers notifications in nested directories', function () {
    expect(NotificationViewer::classes()->all())->toContain(DeepNotification::class);
});

it('skips abstract classes and non-notifications', function () {
    $classes = NotificationViewer::classes()->all();

    expect($classes)
        ->not->toContain(AbstractNotification::class)
        ->not->toContain(NotANotification::class);
});

it('includes manually registered notifications outside the scanned paths', function () {
    config()->set('notification-viewer.paths', []);
    NotificationViewer::register(ScalarNotification::class);

    expect(NotificationViewer::classes()->all())->toBe([ScalarNotification::class]);
});

it('drops excluded notifications', function () {
    NotificationViewer::exclude(ScalarNotification::class);

    expect(NotificationViewer::classes()->all())->not->toContain(ScalarNotification::class);
});

it('reports whether a class is viewable', function () {
    expect(NotificationViewer::contains(ScalarNotification::class))->toBeTrue()
        ->and(NotificationViewer::contains(NotANotification::class))->toBeFalse();
});
