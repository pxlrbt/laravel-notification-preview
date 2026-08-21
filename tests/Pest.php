<?php

declare(strict_types=1);

use pxlrbt\LaravelNotificationViewer\Facades\NotificationViewer;
use pxlrbt\LaravelNotificationViewer\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

beforeEach(fn () => NotificationViewer::flush());
