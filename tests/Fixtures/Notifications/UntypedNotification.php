<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UntypedNotification extends Notification
{
    /**
     * @param  mixed  $model
     */
    public function __construct(public $model) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Untyped');
    }
}
