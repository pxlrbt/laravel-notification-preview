<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\Nested;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeepNotification extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Deep')->line('Nested notification.');
    }
}
