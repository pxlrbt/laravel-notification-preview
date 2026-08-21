<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Models\Customer;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Models\Ticket;

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
