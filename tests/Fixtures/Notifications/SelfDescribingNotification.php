<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use pxlrbt\LaravelNotificationPreview\Variant;

class SelfDescribingNotification extends Notification
{
    public function __construct(public string $tone) {}

    /**
     * @return list<Variant>
     */
    public static function previewVariants(): array
    {
        return [
            Variant::make('friendly', fn () => new self('friendly')),
            Variant::make('formal', fn () => new self('formal')),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("Tone: {$this->tone}")->line("Tone is {$this->tone}.");
    }
}
