<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Channels;

class SmsChannel
{
    public function send(object $notifiable, object $notification): void {}
}
