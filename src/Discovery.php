<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer;

use Composer\Autoload\ClassLoader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use SplFileInfo;
use Throwable;

class Discovery
{
    /**
     * @param  list<string>  $paths  Absolute directory paths, scanned recursively.
     * @param  list<class-string>  $types  Base classes a discovered class must extend.
     * @return Collection<int, class-string>
     */
    public function all(array $paths, array $types): Collection
    {
        if ($types === []) {
            return Collection::make();
        }

        return Collection::make($paths)
            ->flatMap(fn (string $path) => $this->scan($path, $types))
            ->unique()
            ->values();
    }

    /**
     * @param  list<class-string>  $types
     * @return Collection<int, class-string>
     */
    protected function scan(string $path, array $types): Collection
    {
        $namespace = $this->namespaceFor($path);

        if ($namespace === null || ! File::isDirectory($path)) {
            return Collection::make();
        }

        return Collection::make(File::allFiles($path))
            ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
            ->map(fn (SplFileInfo $file) => $this->classFromFile($file, $path, $namespace))
            ->filter()
            ->filter(fn (string $class) => $this->isConcreteSubclass($class, $types))
            ->values();
    }

    /**
     * Derives the namespace of a directory from Composer's PSR-4 map, so the
     * same mapping never has to be repeated in the config.
     */
    protected function namespaceFor(string $path): ?string
    {
        $path = $this->normalize($path);
        $namespace = null;
        $matched = '';

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $roots) {
                foreach ($roots as $root) {
                    $root = $this->normalize($root);

                    if ($path !== $root && ! Str::startsWith($path, $root.'/')) {
                        continue;
                    }

                    // A nested root wins over the shallower one it sits inside.
                    if (strlen($root) < strlen($matched)) {
                        continue;
                    }

                    $matched = $root;
                    $namespace = rtrim($prefix, '\\').str_replace('/', '\\', Str::after($path, $root));
                }
            }
        }

        return $namespace;
    }

    /**
     * Composer's PSR-4 roots keep their `vendor/composer/../..` segments, so
     * both sides have to be resolved before they can be compared.
     */
    protected function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', realpath($path) ?: $path), '/');
    }

    protected function classFromFile(SplFileInfo $file, string $path, string $namespace): ?string
    {
        $relative = Str::after($this->normalize($file->getPathname()), $this->normalize($path).'/');

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
