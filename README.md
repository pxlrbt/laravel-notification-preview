# Laravel Notification Preview

Browse, preview and test-send every notification and mailable in your Laravel app.

![Screenshot](.github/screenshot.png)

## Features

- Discovers every notification and mailable in the directories you point it at.
- Builds each one for you; supply your own data with resolvers and named variants.
- Renders the HTML and the plain-text side of a mail, at desktop, tablet and
  mobile widths.
- Shows every other channel as the JSON payload it would hand its provider.
- Switches locale, edits scalar arguments in place and sends a real test mail to
  any address.
- Never registers its routes in production; gate it anywhere else with an auth
  closure.

## Installation

```bash
composer require pxlrbt/laravel-notification-preview --dev
php artisan vendor:publish --tag=notification-preview-config
```

The routes register themselves and are **always disabled in the production
environment**. Open the preview at `/dev/notifications`.

## Configuration

```php
// config/notification-preview.php
return [
    'enabled' => env('NOTIFICATION_PREVIEW_ENABLED', true),

    // The kinds of classes to look for. Turn either off to skip it entirely.
    'notifications' => env('NOTIFICATION_PREVIEW_NOTIFICATIONS', true),
    'mailables' => env('NOTIFICATION_PREVIEW_MAILABLES', true),

    'url_prefix' => env('NOTIFICATION_PREVIEW_URL_PREFIX', 'dev/notifications'),
    'middleware' => ['web'],

    // Directories, scanned recursively.
    'paths' => [
        app_path('Notifications'),
        app_path('Mail'),
    ],

    // Class names and namespaces to hide.
    'exclude' => [],

    // null falls back to the directories inside lang_path()
    'locales' => null,

    'test_email' => env('NOTIFICATION_PREVIEW_TEST_EMAIL'),
];
```

`paths` takes directories only — namespaces come from Composer's PSR-4 map. Point
it at a whole domain directory if you like; abstract classes and anything that is
neither a notification nor a mailable are skipped.

Both mailable styles work: `envelope()`/`content()` and the older `build()`.

### Other channels

Mail is the only channel rendered as a message. Every other one is shown as the
JSON payload it would hand its provider. Opt them in:

```php
'channels' => ['mail', 'database', 'smsapi'],
```

Each notification gains one tab per channel its `via()` declares. Names match
driver strings and channel classes alike, so `smsapi` also covers an
`SmsapiChannel::class`.

### Authorization

By default there is no check: outside production, anybody who can reach the URL
can open the preview. Narrow that down with an auth closure:

```php
use pxlrbt\LaravelNotificationPreview\Facades\NotificationPreview;

// Local machines only.
NotificationPreview::auth(fn () => app()->isLocal());

// Or specific people, wherever they are.
NotificationPreview::auth(fn (?Request $request) => $request?->user()?->isAdmin());
```

Anyone the closure turns down gets a 403.

### Hiding classes

`exclude` takes fully qualified class names and namespaces. A namespace hides
everything below it:

```php
'exclude' => [
    App\Notifications\Internal\DebugPing::class,   // one class
    'App\Notifications\Internal',                  // the whole namespace
],
```

## Argument resolution

For each constructor parameter the preview tries these sources, in order, and
takes the first one that answers:

| # | Source | Applies to |
| --- | --- | --- |
| 1 | A value edited in the UI | Scalars, enums and dates |
| 2 | A registered resolver | The parameter reference first, then its type |
| 3 | The parameter's default value | Anything with a default |
| 4 | A value derived from the type | See below |
| 5 | A faked scalar derived from the parameter name | `$email`, `$url`, `$name` and `$*Id` get plausible values, everything else gets `'Sample text'` |

Step 4 derives from the type itself:

| Type | Value |
| --- | --- |
| Enum | Its first case |
| Date | `now()` |
| Model | `Model::factory()->make()`, falling back to `Model::query()->first()` |
| Collection | Empty |
| Anything else | Resolved from the container |

## Supplying your own data

### Resolvers

Register these in a service provider, keyed by type:

```php
use pxlrbt\LaravelNotificationPreview\Facades\NotificationPreview;

NotificationPreview::resolve(Order::class, fn () => Order::factory()->make(['id' => 1]));
```

Untyped parameters cannot be matched by type, so key them by parameter instead.
A key registered against a parent class covers every subclass:

```php
NotificationPreview::resolve(
    StateNotification::class.'::$model',
    fn () => Order::factory()->make(),
);
```

The notifiable that notifications are rendered against works the same way:

```php
NotificationPreview::notifiable(fn () => User::factory()->make(['id' => 1]));
```

### Per-notification configuration

`for()` returns one object holding everything the viewer shows about a single
notification — its label, its group and its variants:

```php
use pxlrbt\LaravelNotificationPreview\Facades\NotificationPreview;
use pxlrbt\LaravelNotificationPreview\Variant;

NotificationPreview::for(OrderCancelled::class)
    ->label('Cancellation')
    ->group('Orders')
    ->variants([
        Variant::make('by-customer', fn () => (new OrderCancelled($order))->setReason('customer')),
        Variant::make('by-us', fn () => (new OrderCancelled($order))->setReason('internal')),
    ]);
```

Variants are for notifications that need more than constructor arguments —
setters, a specific state, a particular payload. A variant returns the finished
notification and skips argument resolution entirely.

Labels are derived when you do not set them: from the class name for the
notification, from its key for a variant, so `by-customer` shows as
"By Customer". `label()` and `group()` both take a string or a closure, and a
closure defers a translation until the preview is rendered rather than resolving
it while your service provider boots:

```php
NotificationPreview::for(OrderCancelled::class)
    ->label(fn () => __('notifications.order_cancelled'))
    ->group(fn () => __('notifications.groups.orders'));

Variant::make('by-customer', $factory)->label(fn () => __('variants.by_customer'));
```

Variants can pin their own notifiable:

```php
NotificationPreview::for(OrderShipped::class)->variants([
    Variant::make('to-the-buyer', fn () => new OrderShipped($order))
        ->notifiable(fn () => User::factory()->make(['id' => 2])),
]);
```

### Configuration on the notification itself

To keep the preview data next to the notification, add a static `preview()`
method. Same object, no interface to implement:

```php
use pxlrbt\LaravelNotificationPreview\Preview;
use pxlrbt\LaravelNotificationPreview\Variant;

class OrderShipped extends Notification
{
    public static function preview(Preview $preview): void
    {
        $preview
            ->label('Shipping confirmation')
            ->group('Orders')
            ->variants([
                Variant::make('express', fn () => new self(Order::factory()->express()->make())),
                Variant::make('standard', fn () => new self(Order::factory()->make())),
            ]);
    }
}
```

Both sources merge, and anything registered through `for()` wins — per property,
and per variant key. A class that declares a label and a service provider that
sets only a group end up with both.

### Registering and excluding classes

```php
NotificationPreview::register(SomePackage\Notifications\Welcome::class);

// Same matching rules as the `exclude` config key.
NotificationPreview::exclude(InternalDebugNotification::class);
NotificationPreview::exclude('App\Notifications\Internal');
```

## Testing

```bash
composer test
composer analyse
composer format
```
