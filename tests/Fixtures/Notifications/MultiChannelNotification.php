<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Channels\SmsChannel;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Channels\SmsMessage;

class MultiChannelNotification extends Notification
{
    public function __construct(public int $ticket = 7) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Ticket update')->line('Your ticket moved on.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['ticket' => $this->ticket, 'state' => 'open'];
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return new SmsMessage('Ticket '.$this->ticket.' moved on.', '+49 100 200');
    }
}
