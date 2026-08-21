<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use pxlrbt\LaravelNotificationViewer\Facades\NotificationViewer;
use pxlrbt\LaravelNotificationViewer\NotificationInspector;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\BrokenNotification;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\Nested\DeepNotification;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\ScalarNotification;
use pxlrbt\LaravelNotificationViewer\Tests\Fixtures\Notifications\SelfDescribingNotification;

beforeEach(fn () => $this->inspector = app(NotificationInspector::class));

it('describes a notification with its envelope and source file', function () {
    $details = $this->inspector->describe(DeepNotification::class);

    expect($details)
        ->label->toBe('Deep Notification')
        ->subject->toBe('Deep')
        ->channels->toBe(['mail'])
        ->queued->toBeFalse()
        ->error->toBeNull()
        ->and($details['path'])->toEndWith('Nested/DeepNotification.php');
});

it('captures render failures instead of throwing', function () {
    expect($this->inspector->describe(BrokenNotification::class))
        ->error->toBe('Broken on purpose.')
        ->subject->toBeNull();
});

it('uses registered labels and groups', function () {
    NotificationViewer::label(DeepNotification::class, 'Custom label');
    NotificationViewer::group(DeepNotification::class, 'Custom group');

    expect($this->inspector->describe(DeepNotification::class))
        ->label->toBe('Custom label')
        ->group->toBe('Custom group');
});

it('lists variations and skips parameter editing for them', function () {
    expect($this->inspector->describe(SelfDescribingNotification::class))
        ->variations->toBe(['Friendly', 'Formal'])
        ->params->toBe([]);
});

it('marks scalar parameters editable and objects read only', function () {
    $params = collect($this->inspector->params(ScalarNotification::class))->keyBy('name');

    expect($params['customerName'])
        ->editable->toBeTrue()
        ->input->toBe('text')
        ->value->toBe('Jane Doe');

    expect($params['count'])->input->toBe('number')
        ->and($params['flag'])->input->toBe('checkbox')
        ->and($params['sentAt'])->input->toBe('datetime-local')
        ->and($params['rows'])->editable->toBeFalse();
});

it('offers enum cases as select options', function () {
    $status = collect($this->inspector->params(ScalarNotification::class))->firstWhere('name', 'status');

    expect($status)->input->toBe('select')
        ->and(Arr::pluck($status['options'], 'value'))->toBe(['pending', 'shipped']);
});

it('sorts grouped notifications ahead of ungrouped ones', function () {
    NotificationViewer::group(ScalarNotification::class, 'Billing');

    $groups = array_column($this->inspector->all(), 'group');

    expect($groups[0])->toBe('Billing')
        ->and(array_slice($groups, 1))->each->toBeNull();
});
