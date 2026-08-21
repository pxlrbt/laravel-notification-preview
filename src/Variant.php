<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer;

use Closure;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

class Variant
{
    protected ?Closure $notifiableFactory = null;

    public function __construct(
        public readonly string $label,
        protected readonly Closure $factory,
    ) {}

    public static function make(string $label, Closure $factory): self
    {
        return new self($label, $factory);
    }

    /**
     * Overrides the notifiable used to render this variant.
     */
    public function notifiable(Closure $factory): self
    {
        $this->notifiableFactory = $factory;

        return $this;
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
