<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use pxlrbt\LaravelNotificationPreview\NotificationPreviewServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            NotificationPreviewServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('mail.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/views']);

        $app['config']->set('notification-preview.url_prefix', 'dev/notifications');
        $app['config']->set('notification-preview.paths', [
            __DIR__.'/Fixtures/Notifications',
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
