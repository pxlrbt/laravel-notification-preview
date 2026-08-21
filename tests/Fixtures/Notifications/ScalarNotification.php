<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\StatusEnum;

class ScalarNotification extends Notification
{
    public function __construct(
        public string $customerName,
        public string $invoiceUrl,
        public string $contactEmail,
        public string $orderId,
        public string $other,
        public int $count,
        public float $amount,
        public bool $flag,
        public array $rows,
        public StatusEnum $status,
        public Carbon $sentAt,
        public ?string $nullable = null,
        public string $withDefault = 'default-value',
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Scalar')->line($this->customerName);
    }
}
