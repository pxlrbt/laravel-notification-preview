<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class NotificationViewer
{
    /** @var array<string, Closure> */
    protected array $resolvers = [];

    /** @var array<class-string<Notification>, array<string, Variation>> */
    protected array $variations = [];

    /** @var array<class-string<Notification>, string> */
    protected array $groups = [];

    /** @var array<class-string<Notification>, string> */
    protected array $labels = [];

    /** @var list<class-string<Notification>> */
    protected array $registered = [];

    /** @var list<class-string<Notification>> */
    protected array $excluded = [];

    protected ?Closure $notifiableFactory = null;

    /** @var Collection<int, class-string<Notification>>|null */
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
     * @param  class-string<Notification>  $notification
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
     * @param  class-string<Notification>  $notification
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
     * @param  class-string<Notification>  $notification
     */
    public function group(string $notification, string $group): static
    {
        $this->groups[$notification] = $group;

        return $this;
    }

    /**
     * @param  class-string<Notification>  $notification
     */
    public function groupFor(string $notification): ?string
    {
        return $this->groups[$notification] ?? null;
    }

    /**
     * @param  class-string<Notification>  $notification
     */
    public function label(string $notification, string $label): static
    {
        $this->labels[$notification] = $label;

        return $this;
    }

    /**
     * @param  class-string<Notification>  $notification
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
     * Adds notifications that live outside the scanned paths.
     *
     * @param  class-string<Notification>|list<class-string<Notification>>  $notifications
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
     * @param  class-string<Notification>|list<class-string<Notification>>  $notifications
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
     * @return Collection<int, class-string<Notification>>
     */
    public function classes(): Collection
    {
        return $this->cachedClasses ??= (new DiscoveredNotifications)
            ->all($this->paths())
            ->merge($this->registered)
            ->unique()
            ->reject(fn (string $class) => in_array($class, $this->excluded, true))
            ->filter(fn (string $class) => class_exists($class) && is_subclass_of($class, Notification::class))
            ->sortBy(fn (string $class) => class_basename($class))
            ->values();
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
