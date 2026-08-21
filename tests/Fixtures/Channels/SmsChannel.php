<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Channels;

class SmsChannel
{
    public function send(object $notifiable, object $notification): void {}
}
