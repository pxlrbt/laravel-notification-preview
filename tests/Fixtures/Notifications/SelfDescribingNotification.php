<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications;

use Closure;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SelfDescribingNotification extends Notification
{
    public function __construct(public string $tone) {}

    /**
     * @return array<string, Closure>
     */
    public static function previewVariants(): array
    {
        return [
            'Friendly' => fn () => new self('friendly'),
            'Formal' => fn () => new self('formal'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("Tone: {$this->tone}")->line("Tone is {$this->tone}.");
    }
}
