<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer\Facades;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use pxlrbt\LaravelNotificationViewer\NotificationViewer as NotificationViewerManager;

/**
 * @method static NotificationViewerManager resolve(string $key, Closure $resolver)
 * @method static NotificationViewerManager variations(string $notification, array $variations)
 * @method static NotificationViewerManager group(string $notification, string $group)
 * @method static NotificationViewerManager label(string $notification, string $label)
 * @method static NotificationViewerManager notifiable(Closure $factory)
 * @method static NotificationViewerManager register(string|array $notifications)
 * @method static NotificationViewerManager exclude(string|array $notifications)
 * @method static Collection classes()
 * @method static bool contains(string $class)
 * @method static array locales()
 * @method static void flush()
 *
 * @see NotificationViewerManager
 */
class NotificationViewer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NotificationViewerManager::class;
    }
}
