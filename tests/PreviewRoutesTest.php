<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use pxlrbt\LaravelNotificationPreview\Facades\NotificationPreview;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\BrokenNotification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\Nested\DeepNotification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\NotANotification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\ScalarNotification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\SelfDescribingNotification;

it('lists every discovered notification on the index', function () {
    $this->get('/dev/notifications')
        ->assertOk()
        ->assertSee('Notification Preview')
        ->assertSee('Deep Notification');
});

it('renders a notification preview as html', function () {
    $this->get('/dev/notifications/preview?class='.urlencode(DeepNotification::class))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('Nested notification.');
});

it('renders the selected variant', function () {
    $this->get('/dev/notifications/preview?'.http_build_query([
        'class' => SelfDescribingNotification::class,
        'variant' => 'Formal',
    ]))->assertOk()->assertSee('Tone is formal.');
});

it('renders errors inside the frame instead of failing the request', function () {
    $this->get('/dev/notifications/preview?class='.urlencode(BrokenNotification::class))
        ->assertOk()
        ->assertSee('Broken on purpose.');
});

it('refuses to preview a class it does not know', function () {
    $this->get('/dev/notifications/preview?class='.urlencode(NotANotification::class))
        ->assertNotFound();

    $this->get('/dev/notifications/preview')->assertNotFound();
});

it('sends a test mail carrying the rendered preview', function () {
    $this->post('/dev/notifications/send', [
        'email' => 'someone@example.com',
        'class' => DeepNotification::class,
    ])->assertRedirect()->assertSessionHas('notification-preview.status');

    $messages = Mail::mailer()->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1);

    $sent = $messages[0]->getOriginalMessage();

    expect($sent->getSubject())->toBe('Deep')
        ->and($sent->getTo()[0]->getAddress())->toBe('someone@example.com')
        ->and($sent->getHtmlBody())->toContain('Nested notification.');
});

it('validates the send request', function () {
    $this->post('/dev/notifications/send', [
        'email' => 'not-an-email',
        'class' => NotANotification::class,
    ])->assertSessionHasErrors(['email', 'class']);
});

it('carries ui overrides into the sent mail', function () {
    $this->post('/dev/notifications/send', [
        'email' => 'someone@example.com',
        'class' => SelfDescribingNotification::class,
        'variant' => 'Formal',
    ])->assertRedirect();

    $sent = Mail::mailer()->getSymfonyTransport()->messages()[0]->getOriginalMessage();

    expect($sent->getSubject())->toBe('Tone: formal');
});

it('renders the plain text part of a notification', function () {
    $this->get('/dev/notifications/preview?'.http_build_query([
        'class' => DeepNotification::class,
        'format' => 'text',
    ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Nested notification.');
});

it('keeps serving html when no format is asked for', function () {
    $this->get('/dev/notifications/preview?class='.urlencode(DeepNotification::class))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
});

it('lets everybody in outside production by default', function () {
    // Act & Assert
    $this->get('/dev/notifications')->assertOk();
});

it('denies anybody the auth closure turns down', function () {
    // Arrange
    NotificationPreview::auth(fn () => false);

    // Act & Assert
    $this->get('/dev/notifications')->assertForbidden();
    $this->get('/dev/notifications/preview?class='.urlencode(ScalarNotification::class))->assertForbidden();
});

it('passes the request to the auth closure', function () {
    // Arrange
    $seen = null;
    NotificationPreview::auth(function (?Request $request) use (&$seen) {
        $seen = $request?->path();

        return true;
    });

    // Act
    $this->get('/dev/notifications')->assertOk();

    // Assert
    expect($seen)->toBe('dev/notifications');
});
