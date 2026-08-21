<?php

declare(strict_types=1);

use pxlrbt\LaravelNotificationPreview\Facades\NotificationPreview;
use pxlrbt\LaravelNotificationPreview\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

beforeEach(fn () => NotificationPreview::flush());
