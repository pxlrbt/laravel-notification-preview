<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use pxlrbt\LaravelNotificationPreview\NotificationFactory;
use pxlrbt\LaravelNotificationPreview\NotificationInspector;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\Mail\InvoiceReady;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\Mail\LegacyWelcome;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\Mail\MarkdownReceipt;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\Nested\DeepNotification;

it('builds a mailable from its constructor like a notification', function () {
    expect(app(NotificationFactory::class)->make(InvoiceReady::class))
        ->customerName->toBe('Jane Doe')
        ->invoiceId->toBe('ORD-1001');
});

it('reads the envelope of a modern mailable', function () {
    expect(app(NotificationInspector::class)->describe(InvoiceReady::class))
        ->kind->toBe('mailable')
        ->subject->toBe('Invoice ORD-1001 is ready')
        ->from->toBe('Billing <billing@example.com>')
        ->view->toBe('invoice-ready')
        ->channels->toBe(['mail'])
        ->error->toBeNull();
});

it('reads the envelope of a build style mailable', function () {
    expect(app(NotificationInspector::class)->describe(LegacyWelcome::class))
        ->subject->toBe('Welcome aboard')
        ->view->toBe('legacy-welcome')
        ->error->toBeNull();
});

it('marks notifications as such', function () {
    expect(app(NotificationInspector::class)->describe(LegacyWelcome::class))->kind->toBe('mailable')
        ->and(app(NotificationInspector::class)->describe(
            DeepNotification::class
        ))->kind->toBe('notification');
});

it('previews a mailable over http', function () {
    $this->get('/dev/notifications/preview?class='.urlencode(InvoiceReady::class))
        ->assertOk()
        ->assertSee('invoice ORD-1001 is ready', escape: false);
});

it('edits mailable constructor values from the ui', function () {
    $this->get('/dev/notifications/preview?'.http_build_query([
        'class' => InvoiceReady::class,
        'values' => ['customerName' => 'Ada'],
    ]))->assertOk()->assertSee('Hi Ada,', escape: false);
});

it('sends a mailable as a test mail', function () {
    $this->post('/dev/notifications/send', [
        'email' => 'someone@example.com',
        'class' => InvoiceReady::class,
    ])->assertRedirect();

    $sent = Mail::mailer()->getSymfonyTransport()->messages()[0]->getOriginalMessage();

    expect($sent->getSubject())->toBe('Invoice ORD-1001 is ready')
        ->and($sent->getHtmlBody())->toContain('invoice ORD-1001 is ready');
});

it('renders the plain text part of a markdown mailable', function () {
    $this->get('/dev/notifications/preview?'.http_build_query([
        'class' => MarkdownReceipt::class,
        'format' => 'text',
    ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Thanks for your order.', escape: false);
});

it('reports when a mailable has no plain text part', function () {
    // Plain view mailables carry html only.
    $this->get('/dev/notifications/preview?'.http_build_query([
        'class' => InvoiceReady::class,
        'format' => 'text',
    ]))
        ->assertOk()
        ->assertSee('no plain-text part', escape: false);
});
