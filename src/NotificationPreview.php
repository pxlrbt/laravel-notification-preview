<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotificationPreview
{
    /** @var array<string, Closure> */
    protected array $resolvers = [];

    /** @var array<class-string, Preview> */
    protected array $configs = [];

    /** @var list<class-string> */
    protected array $registered = [];

    /** @var list<string> */
    protected array $excluded = [];

    protected ?Closure $notifiableFactory = null;

    protected ?Closure $authorization = null;

    /** @var Collection<int, class-string>|null */
    protected ?Collection $cachedClasses = null;

    /**
     * Decides who may open the preview. Without one, everybody outside the
     * production environment can, where the routes are never registered.
     */
    public function auth(Closure $callback): static
    {
        $this->authorization = $callback;

        return $this;
    }

    public function allows(?Request $request = null): bool
    {
        if ($this->authorization === null) {
            return ! app()->isProduction();
        }

        return (bool) ($this->authorization)($request);
    }

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
     * Configuration for a single notification: its label, its group and its
     * variants. A static `preview()` on the notification itself is applied
     * first, so whatever is registered here wins.
     *
     * @param  class-string  $notification
     */
    public function for(string $notification): Preview
    {
        if (! isset($this->configs[$notification])) {
            $this->configs[$notification] = new Preview($notification);

            if (method_exists($notification, 'preview')) {
                $notification::preview($this->configs[$notification]);
            }
        }

        return $this->configs[$notification];
    }

    /**
     * The notifiable that notifications are rendered against when a variant
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
     * The base classes the preview looks for, narrowed by the config toggles.
     *
     * @return list<class-string>
     */
    public function types(): array
    {
        $types = [];

        if (config('notification-preview.notifications', true)) {
            $types[] = Notification::class;
        }

        if (config('notification-preview.mailables', true)) {
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
        $excluded = config('notification-preview.exclude', []);

        return $excluded;
    }

    public function contains(string $class): bool
    {
        return $this->classes()->contains($class);
    }

    /**
     * @return list<string>
     */
    protected function paths(): array
    {
        /** @var list<string> $paths */
        $paths = config('notification-preview.paths', []);

        return $paths;
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        /** @var list<string>|null $configured */
        $configured = config('notification-preview.locales');

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
        $this->configs = [];
        $this->registered = [];
        $this->excluded = [];
        $this->notifiableFactory = null;
        $this->authorization = null;
        $this->cachedClasses = null;
    }
}
