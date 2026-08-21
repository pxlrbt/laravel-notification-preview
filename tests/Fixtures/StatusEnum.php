<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests\Fixtures;

enum StatusEnum: string
{
    case Pending = 'pending';
    case Shipped = 'shipped';
}
