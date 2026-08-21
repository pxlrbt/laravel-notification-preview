<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use pxlrbt\LaravelNotificationPreview\Preview;
use pxlrbt\LaravelNotificationPreview\Variant;

class SelfDescribingNotification extends Notification
{
    public function __construct(public string $tone) {}

    public static function preview(Preview $preview): void
    {
        $preview
            ->label(fn () => 'Tone examples')
            ->variants([
                Variant::make('friendly', fn () => new self('friendly')),
                Variant::make('formal', fn () => new self('formal')),
            ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("Tone: {$this->tone}")->line("Tone is {$this->tone}.");
    }
}
