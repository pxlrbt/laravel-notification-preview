<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer;

use Illuminate\Support\Facades\Route;
use pxlrbt\LaravelNotificationViewer\Http\Controllers\NotificationViewerController;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class NotificationViewerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-notification-viewer')
            ->hasConfigFile('notification-viewer')
            ->hasViews('notification-viewer');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(NotificationViewer::class);
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
        $prefix = config('notification-viewer.url_prefix', 'dev/notifications');
        /** @var array<int, string> $middleware */
        $middleware = config('notification-viewer.middleware', ['web']);

        Route::middleware($middleware)
            ->prefix($prefix)
            ->name('notification-viewer.')
            ->group(function (): void {
                Route::get('/', [NotificationViewerController::class, 'index'])->name('index');
                Route::get('/preview', [NotificationViewerController::class, 'preview'])->name('preview');
                Route::post('/send', [NotificationViewerController::class, 'send'])->name('send');
            });
    }

    protected function isEnabled(): bool
    {
        return (bool) config('notification-viewer.enabled', true) && ! $this->app->isProduction();
    }
}
