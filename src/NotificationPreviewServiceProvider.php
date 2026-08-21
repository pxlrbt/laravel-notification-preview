<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview;

use Illuminate\Support\Facades\Route;
use pxlrbt\LaravelNotificationPreview\Http\Controllers\NotificationPreviewController;
use pxlrbt\LaravelNotificationPreview\Http\Middleware\Authorize;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class NotificationPreviewServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-notification-preview')
            ->hasConfigFile('notification-preview')
            ->hasViews('notification-preview');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(NotificationPreview::class);
        $this->app->singleton(NotificationFactory::class);
        $this->app->singleton(PreviewRenderer::class);
        $this->app->singleton(NotificationInspector::class);
    }

    public function packageBooted(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        /** @var string $prefix */
        $prefix = config('notification-preview.url_prefix', 'dev/notifications');
        /** @var array<int, string> $middleware */
        $middleware = config('notification-preview.middleware', ['web']);

        Route::middleware([...$middleware, Authorize::class])
            ->prefix($prefix)
            ->name('notification-preview.')
            ->group(function (): void {
                Route::get('/', [NotificationPreviewController::class, 'index'])->name('index');
                Route::get('/preview', [NotificationPreviewController::class, 'preview'])->name('preview');
                Route::post('/send', [NotificationPreviewController::class, 'send'])->name('send');
            });
    }

    protected function isEnabled(): bool
    {
        return (bool) config('notification-preview.enabled', true) && ! $this->app->isProduction();
    }
}
