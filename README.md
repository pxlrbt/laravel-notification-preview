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

Everybody outside production may open the preview, until you say otherwise:

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

### Variants

When a notification needs more than constructor arguments — setters, a specific
state, a particular payload — register named variants. A variant returns the
finished notification and skips argument resolution entirely:

```php
NotificationPreview::variants(OrderCancelled::class, [
    'By customer' => fn () => (new OrderCancelled($order))->setReason('customer'),
    'By us' => fn () => (new OrderCancelled($order))->setReason('internal'),
]);
```

Variants can pin their own notifiable:

```php
use pxlrbt\LaravelNotificationPreview\Variant;

NotificationPreview::variants(OrderShipped::class, [
    'To the buyer' => Variant::make('To the buyer', fn () => new OrderShipped($order))
        ->notifiable(fn () => User::factory()->make(['id' => 2])),
]);
```

### Variants on the notification itself

If you would rather keep the preview data next to the notification, add a static
`previewVariants()` method. No interface to implement, no import needed:

```php
class OrderShipped extends Notification
{
    public static function previewVariants(): array
    {
        return [
            'Express' => fn () => new self(Order::factory()->express()->make()),
            'Standard' => fn () => new self(Order::factory()->make()),
        ];
    }
}
```

Variants registered through the facade take precedence over these.

### Labels and groups

```php
NotificationPreview::label(OrderShipped::class, 'Shipping confirmation');
NotificationPreview::group(OrderShipped::class, 'Orders');
```

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
