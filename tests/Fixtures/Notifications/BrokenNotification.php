<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use RuntimeException;

class BrokenNotification extends Notification
{
    public function __construct()
    {
        throw new RuntimeException('Broken on purpose.');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return new MailMessage;
    }
}
