<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use SplFileInfo;
use Throwable;

class Discovery
{
    /**
     * @param  array<string, string>  $paths  Absolute directory path => root namespace of that directory.
     * @param  list<class-string>  $types  Base classes a discovered class must extend.
     * @return Collection<int, class-string>
     */
    public function all(array $paths, array $types): Collection
    {
        if ($types === []) {
            return Collection::make();
        }

        return Collection::make($paths)
            ->flatMap(fn (string $namespace, string $path) => $this->scan($path, $namespace, $types))
            ->unique()
            ->values();
    }

    /**
     * @param  list<class-string>  $types
     * @return Collection<int, class-string>
     */
    protected function scan(string $path, string $namespace, array $types): Collection
    {
        if (! File::isDirectory($path)) {
            return Collection::make();
        }

        return Collection::make(File::allFiles($path))
            ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
            ->map(fn (SplFileInfo $file) => $this->classFromFile($file, $path, $namespace))
            ->filter()
            ->filter(fn (string $class) => $this->isConcreteSubclass($class, $types))
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
     * @param  list<class-string>  $types
     *
     * @phpstan-assert-if-true class-string $class
     */
    protected function isConcreteSubclass(string $class, array $types): bool
    {
        try {
            foreach ($types as $type) {
                if (is_subclass_of($class, $type)) {
                    return ! (new ReflectionClass($class))->isAbstract();
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }
}
