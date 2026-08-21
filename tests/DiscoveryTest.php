<?php

declare(strict_types=1);

use pxlrbt\LaravelNotificationViewer\Facades\NotificationViewer;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\AbstractNotification;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\Mail\InvoiceReady;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\Mail\LegacyWelcome;
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

it('discovers mailables alongside notifications', function () {
    expect(NotificationViewer::classes()->all())
        ->toContain(InvoiceReady::class)
        ->toContain(ScalarNotification::class);
});

it('drops mailables when they are turned off', function () {
    config()->set('notification-viewer.mailables', false);

    expect(NotificationViewer::classes()->all())
        ->not->toContain(InvoiceReady::class)
        ->toContain(ScalarNotification::class);
});

it('drops notifications when they are turned off', function () {
    config()->set('notification-viewer.notifications', false);

    expect(NotificationViewer::classes()->all())
        ->toContain(InvoiceReady::class)
        ->not->toContain(ScalarNotification::class);
});

it('finds nothing when both kinds are turned off', function () {
    config()->set('notification-viewer.notifications', false);
    config()->set('notification-viewer.mailables', false);

    expect(NotificationViewer::classes())->toBeEmpty();
});

it('excludes an exact class name from the config', function () {
    config()->set('notification-viewer.exclude', [ScalarNotification::class]);

    expect(NotificationViewer::classes()->all())
        ->not->toContain(ScalarNotification::class)
        ->toContain(InvoiceReady::class);
});

it('excludes a whole namespace from the config', function () {
    config()->set('notification-viewer.exclude', ['pxlrbt\\LaravelNotificationViewer\\Tests\\Fixtures\\Notifications\\Mail']);

    expect(NotificationViewer::classes()->all())
        ->not->toContain(InvoiceReady::class)
        ->not->toContain(LegacyWelcome::class)
        ->toContain(ScalarNotification::class);
});

it('does not treat a partial namespace match as an exclusion', function () {
    config()->set('notification-viewer.exclude', ['pxlrbt\\LaravelNotificationViewer\\Tests\\Fixtures\\Notifications\\Ma']);

    expect(NotificationViewer::classes()->all())->toContain(InvoiceReady::class);
});

it('skips a directory that composer does not autoload', function () {
    // Arrange
    config()->set('notification-viewer.paths', [dirname(__DIR__).'/config']);

    // Act & Assert
    expect(NotificationViewer::classes()->all())->toBe([]);
});
