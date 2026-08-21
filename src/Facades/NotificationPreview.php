<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Facades;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use pxlrbt\LaravelNotificationPreview\NotificationPreview as NotificationPreviewManager;
use pxlrbt\LaravelNotificationPreview\Preview;

/**
 * @method static NotificationPreviewManager auth(Closure $callback)
 * @method static bool allows(?Request $request = null)
 * @method static NotificationPreviewManager resolve(string $key, Closure $resolver)
 * @method static Preview for(string $notification)
 * @method static NotificationPreviewManager notifiable(Closure $factory)
 * @method static NotificationPreviewManager register(string|list<string> $notifications)
 * @method static NotificationPreviewManager exclude(string|list<string> $notifications)
 * @method static bool isExcluded(string $class)
 * @method static list<class-string> types()
 * @method static Collection<int, class-string> classes()
 * @method static bool contains(string $class)
 * @method static list<string> locales()
 * @method static void flush()
 *
 * @see NotificationPreviewManager
 */
class NotificationPreview extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NotificationPreviewManager::class;
    }
}
