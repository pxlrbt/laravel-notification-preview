<?php

declare(strict_types=1);

use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use pxlrbt\LaravelNotificationPreview\Facades\NotificationPreview;
use pxlrbt\LaravelNotificationPreview\NotificationFactory;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Models\Customer;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Models\Ticket;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\ModelNotification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\Nested\DeepNotification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\ScalarNotification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\SelfDescribingNotification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\Notifications\UntypedNotification;
use pxlrbt\LaravelNotificationPreview\Tests\Fixtures\StatusEnum;
use pxlrbt\LaravelNotificationPreview\Variant;

beforeEach(fn () => $this->factory = app(NotificationFactory::class));

it('instantiates notifications without a constructor', function () {
    expect($this->factory->make(DeepNotification::class))->toBeInstanceOf(DeepNotification::class);
});

it('fakes scalars based on the parameter name', function () {
    $notification = $this->factory->make(ScalarNotification::class);

    expect($notification)
        ->customerName->toBe('Jane Doe')
        ->invoiceUrl->toBe('https://example.com')
        ->contactEmail->toBe('preview@example.com')
        ->orderId->toBe('ORD-1001')
        ->other->toBe('Sample text');
});

it('fakes non-string scalars by type', function () {
    $notification = $this->factory->make(ScalarNotification::class);

    expect($notification)
        ->count->toBe(42)
        ->amount->toBe(42.0)
        ->flag->toBeTrue()
        ->rows->toBe([]);
});

it('resolves enums to their first case and dates to now', function () {
    Carbon::setTestNow('2026-01-02 03:04:05');

    $notification = $this->factory->make(ScalarNotification::class);

    expect($notification)->status->toBe(StatusEnum::Pending)
        ->and($notification->sentAt->toDateTimeString())->toBe('2026-01-02 03:04:05');
});

it('prefers default values over faked ones', function () {
    $notification = $this->factory->make(ScalarNotification::class);

    expect($notification)
        ->nullable->toBeNull()
        ->withDefault->toBe('default-value');
});

it('builds models from their factory', function () {
    expect($this->factory->make(ModelNotification::class))
        ->customer->toBeInstanceOf(Customer::class)
        ->and($this->factory->make(ModelNotification::class)->customer->name)->toBe('Factory Customer');
});

it('falls back to the database for models without a factory', function () {
    Ticket::query()->create(['subject' => 'From database']);

    expect($this->factory->make(ModelNotification::class)->ticket->subject)->toBe('From database');
});

it('applies registered resolvers by type', function () {
    NotificationPreview::resolve(Customer::class, fn () => new Customer(['name' => 'Resolved']));

    expect($this->factory->make(ModelNotification::class)->customer->name)->toBe('Resolved');
});

it('applies registered resolvers by parameter reference for untyped parameters', function () {
    NotificationPreview::resolve(UntypedNotification::class.'::$model', fn () => ['resolved' => true]);

    expect($this->factory->make(UntypedNotification::class))->model->toBe(['resolved' => true]);
});

it('applies resolvers registered against a parent class', function () {
    NotificationPreview::resolve(Notification::class.'::$model', fn () => 'from parent');

    expect($this->factory->make(UntypedNotification::class))->model->toBe('from parent');
});

it('lets ui overrides win over resolvers', function () {
    NotificationPreview::resolve(ScalarNotification::class.'::$customerName', fn () => 'Resolved');

    expect($this->factory->make(ScalarNotification::class, overrides: ['customerName' => 'Overridden']))
        ->customerName->toBe('Overridden');
});

it('casts overrides to the declared parameter type', function () {
    $notification = $this->factory->make(ScalarNotification::class, overrides: [
        'count' => '7',
        'amount' => '1.5',
        'flag' => 'false',
        'status' => 'shipped',
        'sentAt' => '2026-03-04T05:06',
    ]);

    expect($notification)
        ->count->toBe(7)
        ->amount->toBe(1.5)
        ->flag->toBeFalse()
        ->status->toBe(StatusEnum::Shipped)
        ->and($notification->sentAt->format('Y-m-d H:i'))->toBe('2026-03-04 05:06');
});

it('ignores overrides for parameters that cannot round-trip through a query string', function () {
    NotificationPreview::resolve(Customer::class, fn () => new Customer(['name' => 'Resolved']));

    expect($this->factory->make(ModelNotification::class, overrides: ['customer' => 'nonsense']))
        ->customer->name->toBe('Resolved');
});

it('short circuits reflection when a variant is registered', function () {
    NotificationPreview::variants(ScalarNotification::class, [
        Variant::make('custom', fn () => new ScalarNotification(
            'Registered', '', '', '', '', 1, 1.0, false, [], StatusEnum::Shipped, Carbon::now(),
        )),
    ]);

    expect($this->factory->make(ScalarNotification::class, 'custom'))->customerName->toBe('Registered');
});

it('uses the first variant when none is selected', function () {
    expect($this->factory->make(SelfDescribingNotification::class))->tone->toBe('friendly');
});

it('picks up variants declared on the notification itself', function () {
    expect($this->factory->make(SelfDescribingNotification::class, 'formal'))->tone->toBe('formal');
});

it('lets registered variants override ones declared on the notification', function () {
    NotificationPreview::variants(SelfDescribingNotification::class, [
        Variant::make('formal', fn () => new SelfDescribingNotification('registered')),
    ]);

    expect($this->factory->make(SelfDescribingNotification::class, 'formal'))->tone->toBe('registered');
});
