<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\BrokenNotification;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\Nested\DeepNotification;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\NotANotification;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\SelfDescribingNotification;

it('lists every discovered notification on the index', function () {
    $this->get('/dev/notifications')
        ->assertOk()
        ->assertSee('Notification Viewer')
        ->assertSee('Deep Notification');
});

it('renders a notification preview as html', function () {
    $this->get('/dev/notifications/preview?class='.urlencode(DeepNotification::class))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('Nested notification.');
});

it('renders the selected variation', function () {
    $this->get('/dev/notifications/preview?'.http_build_query([
        'class' => SelfDescribingNotification::class,
        'variation' => 'Formal',
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
    ])->assertRedirect()->assertSessionHas('notification-viewer.status');

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
        'variation' => 'Formal',
    ])->assertRedirect();

    $sent = Mail::mailer()->getSymfonyTransport()->messages()[0]->getOriginalMessage();

    expect($sent->getSubject())->toBe('Tone: formal');
});
