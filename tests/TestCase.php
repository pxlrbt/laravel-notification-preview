<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use pxlrbt\LaravelNotificationViewer\NotificationViewerServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            NotificationViewerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('mail.default', 'array');
        $app['config']->set('database.default', 'testing');

        $app['config']->set('notification-viewer.url_prefix', 'dev/notifications');
        $app['config']->set('notification-viewer.paths', [
            __DIR__.'/Fixtures/Notifications' => 'pxlrbt\\LaravelNotificationViewer\\Tests\\Fixtures\\Notifications',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('subject');
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
}
