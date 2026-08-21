<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotificationViewer
{
    /** @var array<string, Closure> */
    protected array $resolvers = [];

    /** @var array<class-string, array<string, Variation>> */
    protected array $variations = [];

    /** @var array<class-string, string> */
    protected array $groups = [];

    /** @var array<class-string, string> */
    protected array $labels = [];

    /** @var list<class-string> */
    protected array $registered = [];

    /** @var list<string> */
    protected array $excluded = [];

    protected ?Closure $notifiableFactory = null;

    /** @var Collection<int, class-string>|null */
    protected ?Collection $cachedClasses = null;

    /**
     * Registers a value used whenever a constructor parameter of the given type
     * needs to be resolved. The key is either a type name (`Appointment::class`)
     * or a parameter reference (`Foo::class . '::$model'`) for untyped parameters.
     */
    public function resolve(string $key, Closure $resolver): static
    {
        $this->resolvers[ltrim($key, '\\')] = $resolver;

        return $this;
    }

    public function hasResolver(string $key): bool
    {
        return isset($this->resolvers[ltrim($key, '\\')]);
    }

    public function callResolver(string $key): mixed
    {
        return ($this->resolvers[ltrim($key, '\\')])();
    }

    /**
     * Registers named variations for a notification. Each closure must return a
     * fully constructed notification, which lets you call setters that the
     * constructor does not cover.
     *
     * @param  array<string, Closure|Variation>  $variations
     * @param  class-string  $notification
     */
    public function variations(string $notification, array $variations): static
    {
        foreach ($variations as $label => $variation) {
            $this->variations[$notification][$label] = $variation instanceof Variation
                ? $variation
                : Variation::make($label, $variation);
        }

        return $this;
    }

    /**
     * @param  class-string  $notification
     * @return array<string, Variation>
     */
    public function variationsFor(string $notification): array
    {
        $registered = $this->variations[$notification] ?? [];

        if (method_exists($notification, 'previewVariations')) {
            /** @var array<string, Closure|Variation> $declared */
            $declared = $notification::previewVariations();

            foreach ($declared as $label => $variation) {
                $registered[$label] ??= $variation instanceof Variation
                    ? $variation
                    : Variation::make($label, $variation);
            }
        }

        return $registered;
    }

    /**
     * @param  class-string  $notification
     */
    public function group(string $notification, string $group): static
    {
        $this->groups[$notification] = $group;

        return $this;
    }

    /**
     * @param  class-string  $notification
     */
    public function groupFor(string $notification): ?string
    {
        return $this->groups[$notification] ?? null;
    }

    /**
     * @param  class-string  $notification
     */
    public function label(string $notification, string $label): static
    {
        $this->labels[$notification] = $label;

        return $this;
    }

    /**
     * @param  class-string  $notification
     */
    public function labelFor(string $notification): ?string
    {
        return $this->labels[$notification] ?? null;
    }

    /**
     * The notifiable that notifications are rendered against when a variation
     * does not provide its own.
     */
    public function notifiable(Closure $factory): static
    {
        $this->notifiableFactory = $factory;

        return $this;
    }

    public function resolveNotifiable(): object
    {
        if ($this->notifiableFactory !== null) {
            return ($this->notifiableFactory)();
        }

        /** @var class-string<Model>|null $model */
        $model = config('auth.providers.users.model');

        if ($model !== null && class_exists($model)) {
            $notifiable = app(NotificationFactory::class)->makeModel($model);

            if ($notifiable !== null) {
                return $notifiable;
            }
        }

        return new AnonymousNotifiable;
    }

    /**
     * Adds notifications or mailables that live outside the scanned paths.
     *
     * @param  class-string|list<class-string>  $notifications
     */
    public function register(string|array $notifications): static
    {
        foreach ((array) $notifications as $notification) {
            $this->registered[] = $notification;
        }

        $this->cachedClasses = null;

        return $this;
    }

    /**
     * Hides notifications or mailables. Accepts class names or namespaces.
     *
     * @param  string|list<string>  $notifications
     */
    public function exclude(string|array $notifications): static
    {
        foreach ((array) $notifications as $notification) {
            $this->excluded[] = $notification;
        }

        $this->cachedClasses = null;

        return $this;
    }

    /**
     * @return Collection<int, class-string>
     */
    public function classes(): Collection
    {
        $types = $this->types();

        return $this->cachedClasses ??= (new Discovery)
            ->all($this->paths(), $types)
            ->merge($this->registered)
            ->unique()
            ->filter(fn (string $class) => class_exists($class) && $this->isOfType($class, $types))
            ->reject(fn (string $class) => $this->isExcluded($class))
            ->sortBy(fn (string $class) => class_basename($class))
            ->values();
    }

    /**
     * The base classes the viewer looks for, narrowed by the config toggles.
     *
     * @return list<class-string>
     */
    public function types(): array
    {
        $types = [];

        if (config('notification-viewer.notifications', true)) {
            $types[] = Notification::class;
        }

        if (config('notification-viewer.mailables', true)) {
            $types[] = Mailable::class;
        }

        return $types;
    }

    /**
     * @param  list<class-string>  $types
     */
    protected function isOfType(string $class, array $types): bool
    {
        foreach ($types as $type) {
            if (is_subclass_of($class, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Matches an exact class name, or a namespace that the class sits under.
     */
    public function isExcluded(string $class): bool
    {
        $class = ltrim($class, '\\');

        foreach ([...$this->excluded, ...$this->configuredExclusions()] as $pattern) {
            $pattern = trim($pattern, '\\');

            if ($class === $pattern || Str::startsWith($class, $pattern.'\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function configuredExclusions(): array
    {
        /** @var list<string> $excluded */
        $excluded = config('notification-viewer.exclude', []);

        return $excluded;
    }

    public function contains(string $class): bool
    {
        return $this->classes()->contains($class);
    }

    /**
     * @return array<string, string>
     */
    protected function paths(): array
    {
        /** @var array<string, string> $paths */
        $paths = config('notification-viewer.paths', []);

        return $paths;
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        /** @var list<string>|null $configured */
        $configured = config('notification-viewer.locales');

        if ($configured !== null) {
            return $configured;
        }

        /** @var list<string> */
        return Collection::make(glob(lang_path('*'), GLOB_ONLYDIR) ?: [])
            ->map(fn (string $path) => basename($path))
            ->sort()
            ->values()
            ->all();
    }

    public function flush(): void
    {
        $this->resolvers = [];
        $this->variations = [];
        $this->groups = [];
        $this->labels = [];
        $this->registered = [];
        $this->excluded = [];
        $this->notifiableFactory = null;
        $this->cachedClasses = null;
    }
}
