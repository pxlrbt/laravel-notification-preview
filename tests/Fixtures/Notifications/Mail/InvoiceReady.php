<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Envelope/content style mailable.
 */
class InvoiceReady extends Mailable
{
    public function __construct(public string $customerName, public string $invoiceId) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('billing@example.com', 'Billing'),
            subject: "Invoice {$this->invoiceId} is ready",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'invoice-ready');
    }
}
