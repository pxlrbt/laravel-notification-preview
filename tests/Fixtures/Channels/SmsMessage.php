<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Channels;

class SmsMessage
{
    public function __construct(public string $body, public string $recipient) {}
}
