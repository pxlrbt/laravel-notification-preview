<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview;

use Closure;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class Variant
{
    protected Closure|string|null $label = null;

    protected ?Closure $notifiableFactory = null;

    public function __construct(
        public readonly string $key,
        protected readonly Closure $factory,
    ) {}

    public static function make(string $key, Closure $factory): self
    {
        return new self($key, $factory);
    }

    /**
     * Overrides the label derived from the key. Pass a closure to defer a
     * translation until the variant is rendered.
     */
    public function label(Closure|string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Overrides the notifiable used to render this variant.
     */
    public function notifiable(Closure $factory): self
    {
        $this->notifiableFactory = $factory;

        return $this;
    }

    public function resolveLabel(): string
    {
        if ($this->label instanceof Closure) {
            return (string) ($this->label)();
        }

        return $this->label ?? Str::headline($this->key);
    }

    public function resolve(): Notification|Mailable
    {
        return ($this->factory)();
    }

    public function resolveNotifiable(): ?object
    {
        return $this->notifiableFactory === null
            ? null
            : ($this->notifiableFactory)();
    }
}
