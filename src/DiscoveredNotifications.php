<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use SplFileInfo;
use Throwable;

class DiscoveredNotifications
{
    /**
     * @param  array<string, string>  $paths  Absolute directory path => root namespace of that directory.
     * @return Collection<int, class-string<Notification>>
     */
    public function all(array $paths): Collection
    {
        return Collection::make($paths)
            ->flatMap(fn (string $namespace, string $path) => $this->scan($path, $namespace))
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, class-string<Notification>>
     */
    protected function scan(string $path, string $namespace): Collection
    {
        if (! File::isDirectory($path)) {
            return Collection::make();
        }

        return Collection::make(File::allFiles($path))
            ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
            ->map(fn (SplFileInfo $file) => $this->classFromFile($file, $path, $namespace))
            ->filter()
            ->filter(fn (string $class) => $this->isConcreteNotification($class))
            ->values();
    }

    protected function classFromFile(SplFileInfo $file, string $path, string $namespace): ?string
    {
        $base = rtrim(str_replace('\\', '/', $path), '/');
        $relative = Str::after(str_replace('\\', '/', $file->getPathname()), $base.'/');

        $class = rtrim($namespace, '\\').'\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

        try {
            return class_exists($class) ? $class : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @phpstan-assert-if-true class-string<Notification> $class
     */
    protected function isConcreteNotification(string $class): bool
    {
        try {
            if (! is_subclass_of($class, Notification::class)) {
                return false;
            }

            return ! (new ReflectionClass($class))->isAbstract();
        } catch (Throwable) {
            return false;
        }
    }
}
