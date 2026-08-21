<?php

declare(strict_types=1);

use pxlrbt\LaravelNotificationPreview\NotificationInspector;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Channels\SmsChannel;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\MultiChannelNotification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\ScalarNotification;

function formats(string $class): array
{
    return resolve(NotificationInspector::class)->describe($class)['formats'];
}

function previewOf(string $class, string $format): string
{
    return test()->get('/dev/notifications/preview?class='.urlencode($class).'&format='.urlencode($format))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->getContent();
}

it('offers only the mail bodies until the config opts a channel in', function () {
    // Act
    $values = array_column(formats(MultiChannelNotification::class), 'value');

    // Assert
    expect($values)->toBe(['html', 'text']);
});

it('offers a body per configured channel the notification declares', function () {
    // Arrange
    config()->set('notification-preview.channels', ['mail', 'database', 'sms']);

    // Act
    $formats = formats(MultiChannelNotification::class);

    // Assert
    expect($formats)->toBe([
        ['value' => 'html', 'label' => 'HTML'],
        ['value' => 'text', 'label' => 'Text'],
        ['value' => 'database', 'label' => 'Database'],
        ['value' => SmsChannel::class, 'label' => 'Sms'],
    ]);
});

it('drops the mail bodies when mail is not configured', function () {
    // Arrange
    config()->set('notification-preview.channels', ['database']);

    // Act
    $values = array_column(formats(MultiChannelNotification::class), 'value');

    // Assert
    expect($values)->toBe(['database']);
});

it('renders the database payload as json, falling back to toArray', function () {
    // Arrange
    config()->set('notification-preview.channels', ['mail', 'database']);

    // Act
    $body = previewOf(MultiChannelNotification::class, 'database');

    // Assert
    expect($body)->toBe(<<<'JSON'
    {
        "ticket": 7,
        "state": "open"
    }
    JSON);
});

it('renders a channel class payload from its public properties', function () {
    // Arrange
    config()->set('notification-preview.channels', ['sms']);

    // Act
    $body = previewOf(MultiChannelNotification::class, SmsChannel::class);

    // Assert
    expect($body)
        ->toContain('"body": "Ticket 7 moved on."')
        ->toContain('"recipient": "+49 100 200"');
});

it('refuses a channel the config did not opt in', function () {
    // Act & Assert
    expect(previewOf(MultiChannelNotification::class, 'database'))
        ->toBe('This notification has no Database payload.');
});

it('refuses a channel the notification does not declare', function () {
    // Arrange
    config()->set('notification-preview.channels', ['mail', 'slack']);

    // Act & Assert
    expect(previewOf(ScalarNotification::class, 'slack'))
        ->toBe('This notification has no Slack payload.');
});
