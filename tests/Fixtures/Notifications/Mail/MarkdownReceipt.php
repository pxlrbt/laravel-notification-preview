<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Markdown mailable, which carries a plain-text alternative.
 */
class MarkdownReceipt extends Mailable
{
    public function __construct(public string $reference) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Receipt {$this->reference}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'receipt');
    }
}
