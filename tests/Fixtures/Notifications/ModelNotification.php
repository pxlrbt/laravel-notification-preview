<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Models\Customer;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Models\Ticket;

class ModelNotification extends Notification
{
    public function __construct(
        public Customer $customer,
        public Ticket $ticket,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("Ticket for {$this->customer->name}");
    }
}
