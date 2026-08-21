# Laravel Notification Viewer

Browse, preview and test-send every notification in your Laravel app.

The viewer scans your notification directories, builds each notification by
resolving its constructor arguments from their types, and renders the mail it
produces. Notifications whose arguments reflection cannot guess get their data
from resolvers or variations you register.

![Screenshot](.github/screenshot.png)

## Installation

```bash
composer require pxlrbt/laravel-notification-viewer --dev
php artisan vendor:publish --tag=notification-viewer-config
```

The viewer registers its routes automatically and is **always disabled in the
production environment**.

## Configuration

```php
// config/notification-viewer.php
return [
    'enabled' => env('NOTIFICATION_VIEWER_ENABLED', true),
    'url_prefix' => env('NOTIFICATION_VIEWER_URL_PREFIX', 'dev/notifications'),
    'middleware' => ['web'],

    // Directory => the PSR-4 root namespace of that directory. Scanned recursively.
    'paths' => [
        app_path('Notifications') => 'App\\Notifications',
    ],

    // null falls back to the directories inside lang_path()
    'locales' => null,

    'test_email' => env('NOTIFICATION_VIEWER_TEST_EMAIL'),
];
```

Only concrete subclasses of `Illuminate\Notifications\Notification` are listed;
abstract classes and everything else is skipped. That means you can point
`paths` at a whole domain directory without filtering it yourself.

## Argument resolution

For each constructor parameter the viewer tries, in order:

1. A value edited in the UI (scalars, enums and dates only).
2. A registered resolver for the parameter reference, then for its type.
3. The parameter's default value.
4. A value derived from the type — enums use their first case, dates use `now()`,
   models use `Model::factory()->make()` and fall back to `Model::query()->first()`,
   collections come back empty, anything else is resolved from the container.
5. A faked scalar derived from the parameter name — `$email`, `$url`, `$name` and
   `$*Id` get plausible values, everything else gets `'Sample text'`.

## Supplying your own data

### Resolvers

Register these in a service provider, keyed by type:

```php
use pxlrbt\LaravelNotificationViewer\Facades\NotificationViewer;

NotificationViewer::resolve(Order::class, fn () => Order::factory()->make(['id' => 1]));
```

Untyped parameters cannot be matched by type, so key them by parameter instead.
A key registered against a parent class covers every subclass:

```php
NotificationViewer::resolve(
    StateNotification::class.'::$model',
    fn () => Order::factory()->make(),
);
```

The notifiable that notifications are rendered against works the same way:

```php
NotificationViewer::notifiable(fn () => User::factory()->make(['id' => 1]));
```

### Variations

When a notification needs more than constructor arguments — setters, a specific
state, a particular payload — register named variations. A variation returns the
finished notification and skips argument resolution entirely:

```php
NotificationViewer::variations(OrderCancelled::class, [
    'By customer' => fn () => (new OrderCancelled($order))->setReason('customer'),
    'By us' => fn () => (new OrderCancelled($order))->setReason('internal'),
]);
```

Variations can pin their own notifiable:

```php
use pxlrbt\LaravelNotificationViewer\Variation;

NotificationViewer::variations(OrderShipped::class, [
    'To the buyer' => Variation::make('To the buyer', fn () => new OrderShipped($order))
        ->notifiable(fn () => User::factory()->make(['id' => 2])),
]);
```

### Variations on the notification itself

If you would rather keep the preview data next to the notification, add a static
`previewVariations()` method. No interface to implement, no import needed:

```php
class OrderShipped extends Notification
{
    public static function previewVariations(): array
    {
        return [
            'Express' => fn () => new self(Order::factory()->express()->make()),
            'Standard' => fn () => new self(Order::factory()->make()),
        ];
    }
}
```

Variations registered through the facade take precedence over these.

### Labels and groups

```php
NotificationViewer::label(OrderShipped::class, 'Shipping confirmation');
NotificationViewer::group(OrderShipped::class, 'Orders');
```

### Registering and excluding classes

```php
NotificationViewer::register(SomePackage\Notifications\Welcome::class);
NotificationViewer::exclude(InternalDebugNotification::class);
```

## Testing

```bash
composer test
composer analyse
composer format
```
