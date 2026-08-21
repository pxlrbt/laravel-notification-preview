<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview;

use Closure;
use Illuminate\Support\Str;

/**
 * How one notification or mailable appears in the viewer. Reachable through
 * `NotificationPreview::for()` and through a static `preview()` on the class
 * itself, which is applied first so that registered configuration wins.
 */
class Preview
{
    /** @var array<string, Variant> */
    protected array $variants = [];

    protected Closure|string|null $label = null;

    protected Closure|string|null $group = null;

    /**
     * @param  class-string  $notification
     */
    public function __construct(protected readonly string $notification) {}

    /**
     * Overrides the label derived from the class name. Pass a closure to defer a
     * translation until the preview is rendered.
     */
    public function label(Closure|string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Buckets the notification under a heading on the index.
     */
    public function group(Closure|string $group): static
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Registering a key twice keeps the last one, which is how registered
     * variants beat the ones a notification declares for itself.
     *
     * @param  list<Variant>  $variants
     */
    public function variants(array $variants): static
    {
        foreach ($variants as $variant) {
            $this->variants[$variant->key] = $variant;
        }

        return $this;
    }

    public function resolveLabel(): string
    {
        return $this->resolve($this->label) ?? Str::headline(class_basename($this->notification));
    }

    public function resolveGroup(): ?string
    {
        return $this->resolve($this->group);
    }

    /**
     * @return array<string, Variant>
     */
    public function resolveVariants(): array
    {
        return $this->variants;
    }

    protected function resolve(Closure|string|null $value): ?string
    {
        return $value instanceof Closure ? (string) $value() : $value;
    }
}
