<?php

declare(strict_types=1);

use pxlrbt\LaravelNotificationPreview\Facades\NotificationPreview;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\AbstractNotification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\Mail\InvoiceReady;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\Mail\LegacyWelcome;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\Nested\DeepNotification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\NotANotification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\ScalarNotification;

it('discovers concrete notifications in the configured paths', function () {
    expect(NotificationPreview::classes()->all())->toContain(ScalarNotification::class);
});

it('discovers notifications in nested directories', function () {
    expect(NotificationPreview::classes()->all())->toContain(DeepNotification::class);
});

it('skips abstract classes and non-notifications', function () {
    $classes = NotificationPreview::classes()->all();

    expect($classes)
        ->not->toContain(AbstractNotification::class)
        ->not->toContain(NotANotification::class);
});

it('includes manually registered notifications outside the scanned paths', function () {
    config()->set('notification-preview.paths', []);
    NotificationPreview::register(ScalarNotification::class);

    expect(NotificationPreview::classes()->all())->toBe([ScalarNotification::class]);
});

it('drops excluded notifications', function () {
    NotificationPreview::exclude(ScalarNotification::class);

    expect(NotificationPreview::classes()->all())->not->toContain(ScalarNotification::class);
});

it('reports whether a class is viewable', function () {
    expect(NotificationPreview::contains(ScalarNotification::class))->toBeTrue()
        ->and(NotificationPreview::contains(NotANotification::class))->toBeFalse();
});

it('discovers mailables alongside notifications', function () {
    expect(NotificationPreview::classes()->all())
        ->toContain(InvoiceReady::class)
        ->toContain(ScalarNotification::class);
});

it('drops mailables when they are turned off', function () {
    config()->set('notification-preview.mailables', false);

    expect(NotificationPreview::classes()->all())
        ->not->toContain(InvoiceReady::class)
        ->toContain(ScalarNotification::class);
});

it('drops notifications when they are turned off', function () {
    config()->set('notification-preview.notifications', false);

    expect(NotificationPreview::classes()->all())
        ->toContain(InvoiceReady::class)
        ->not->toContain(ScalarNotification::class);
});

it('finds nothing when both kinds are turned off', function () {
    config()->set('notification-preview.notifications', false);
    config()->set('notification-preview.mailables', false);

    expect(NotificationPreview::classes())->toBeEmpty();
});

it('excludes an exact class name from the config', function () {
    config()->set('notification-preview.exclude', [ScalarNotification::class]);

    expect(NotificationPreview::classes()->all())
        ->not->toContain(ScalarNotification::class)
        ->toContain(InvoiceReady::class);
});

it('excludes a whole namespace from the config', function () {
    config()->set('notification-preview.exclude', ['pxlrbt\\LaravelNotificationPreview\\Tests\\Fixtures\\Notifications\\Mail']);

    expect(NotificationPreview::classes()->all())
        ->not->toContain(InvoiceReady::class)
        ->not->toContain(LegacyWelcome::class)
        ->toContain(ScalarNotification::class);
});

it('does not treat a partial namespace match as an exclusion', function () {
    config()->set('notification-preview.exclude', ['pxlrbt\\LaravelNotificationPreview\\Tests\\Fixtures\\Notifications\\Ma']);

    expect(NotificationPreview::classes()->all())->toContain(InvoiceReady::class);
});

it('skips a directory that composer does not autoload', function () {
    // Arrange
    config()->set('notification-preview.paths', [dirname(__DIR__).'/config']);

    // Act & Assert
    expect(NotificationPreview::classes()->all())->toBe([]);
});
