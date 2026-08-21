<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationViewer;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use ReflectionClass;
use ReflectionParameter;
use Throwable;

class NotificationInspector
{
    public function __construct(
        protected NotificationViewer $viewer,
        protected NotificationFactory $factory,
    ) {}

    /**
     * Metadata for every discovered notification, used to render the viewer shell.
     *
     * ponytail: renders every notification once to read its subject line. Fine for
     * a few dozen classes; move to a lazy per-row request if the index gets slow.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->viewer->classes()
            ->map(fn (string $class) => $this->describe($class))
            ->all();
    }

    /**
     * @param  class-string<Notification>  $class
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function describe(string $class, ?string $variation = null, array $overrides = []): array
    {
        $reflection = new ReflectionClass($class);
        $variations = array_keys($this->viewer->variationsFor($class));

        $details = [
            'class' => $class,
            'label' => $this->viewer->labelFor($class) ?? $this->humanize(class_basename($class)),
            'group' => $this->viewer->groupFor($class),
            'path' => $this->relativePath($reflection->getFileName() ?: ''),
            'variations' => $variations,
            'queued' => $reflection->implementsInterface(ShouldQueue::class),
            'params' => $variations === [] ? $this->params($class, $overrides) : [],
            'subject' => null,
            'from' => null,
            'channels' => [],
            'view' => null,
            'error' => null,
        ];

        try {
            $notification = $this->factory->make($class, $variation, $overrides);
            $notifiable = $this->notifiableFor($class, $variation);

            $details['channels'] = method_exists($notification, 'via')
                ? (array) $notification->via($notifiable)
                : [];

            $mail = method_exists($notification, 'toMail')
                ? $notification->toMail($notifiable)
                : null;

            if ($mail instanceof MailMessage) {
                $details['subject'] = $mail->subject;
                $details['from'] = $this->formatFrom($mail);
                $details['view'] = $mail->markdown ?: (is_string($mail->view) ? $mail->view : null);
            }
        } catch (Throwable $exception) {
            $details['error'] = $exception->getMessage();
        }

        return $details;
    }

    /**
     * @param  class-string<Notification>  $class
     */
    public function notifiableFor(string $class, ?string $variation = null): object
    {
        $variations = $this->viewer->variationsFor($class);
        $selected = $variation !== null
            ? ($variations[$variation] ?? null)
            : ($variations === [] ? null : reset($variations));

        return $selected?->resolveNotifiable() ?? $this->viewer->resolveNotifiable();
    }

    /**
     * Constructor parameters and whether the UI may edit them.
     *
     * @param  class-string<Notification>  $class
     * @param  array<string, mixed>  $overrides
     * @return list<array<string, mixed>>
     */
    public function params(string $class, array $overrides = []): array
    {
        $constructor = (new ReflectionClass($class))->getConstructor();

        if ($constructor === null) {
            return [];
        }

        return array_map(function (ReflectionParameter $parameter) use ($class, $overrides): array {
            $editable = $this->factory->isOverridable($parameter);

            return [
                'name' => $parameter->getName(),
                'type' => $this->factory->namedTypeName($parameter->getType()) ?? 'mixed',
                'editable' => $editable,
                'input' => $this->factory->inputType($parameter),
                'options' => $this->factory->enumOptions($parameter),
                'value' => $editable ? $this->stringify($this->safeResolve($class, $parameter, $overrides)) : null,
                'preview' => $editable ? null : $this->stringify($this->safeResolve($class, $parameter, $overrides)),
            ];
        }, $constructor->getParameters());
    }

    /**
     * @param  class-string<Notification>  $class
     * @param  array<string, mixed>  $overrides
     */
    protected function safeResolve(string $class, ReflectionParameter $parameter, array $overrides): mixed
    {
        try {
            return $this->factory->resolveParameter($class, $parameter, $overrides);
        } catch (Throwable) {
            return null;
        }
    }

    protected function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \UnitEnum => $value->name,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d\TH:i'),
            is_object($value) => class_basename($value),
            is_array($value) => 'array('.count($value).')',
            default => gettype($value),
        };
    }

    protected function formatFrom(MailMessage $mail): ?string
    {
        $address = $mail->from[0] ?? config('mail.from.address');
        $name = $mail->from[1] ?? config('mail.from.name');

        if (! is_string($address)) {
            return null;
        }

        return is_string($name) && $name !== '' ? "{$name} <{$address}>" : $address;
    }

    protected function relativePath(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR);
    }

    protected function humanize(string $name): string
    {
        return trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $name) ?? $name);
    }
}
