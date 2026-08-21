<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use ReflectionClass;
use ReflectionParameter;
use Throwable;
use UnitEnum;

class NotificationInspector
{
    public function __construct(
        protected NotificationPreview $registry,
        protected NotificationFactory $factory,
        protected PreviewRenderer $renderer,
    ) {}

    /**
     * Metadata for every discovered class, used to render the preview shell.
     *
     * ponytail: renders every entry once to read its subject line. Fine for a few
     * dozen classes; move to a lazy per-row request if the index gets slow.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        /** @var list<array<string, mixed>> */
        return $this->registry->classes()
            ->map(fn (string $class) => $this->describe($class))
            // Grouped entries first, in group order, then the ungrouped rest.
            ->sortBy(fn (array $row) => ($row['group'] ?? "\u{FFFF}")."\u{0000}".$row['label'])
            ->values()
            ->all();
    }

    /**
     * @param  class-string  $class
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function describe(string $class, ?string $variant = null, array $overrides = []): array
    {
        $reflection = new ReflectionClass($class);
        $preview = $this->registry->for($class);
        $variants = $preview->resolveVariants();

        $details = [
            'class' => $class,
            'kind' => $reflection->isSubclassOf(Mailable::class) ? 'mailable' : 'notification',
            'label' => $preview->resolveLabel(),
            'group' => $preview->resolveGroup(),
            'path' => $this->relativePath($reflection->getFileName() ?: ''),
            'variants' => array_values(array_map(fn (Variant $variant) => [
                'value' => $variant->key,
                'label' => $variant->resolveLabel(),
            ], $variants)),
            'queued' => $reflection->implementsInterface(ShouldQueue::class),
            'params' => $variants === [] ? $this->params($class, $overrides) : [],
            'subject' => null,
            'from' => null,
            'view' => null,
            'channels' => [],
            'formats' => [],
            'error' => null,
        ];

        try {
            $rendered = $this->renderer->render(
                $this->factory->make($class, $variant, $overrides),
                $this->notifiableFor($class, $variant),
            );

            $details['subject'] = $rendered['subject'];
            $details['from'] = $rendered['from'];
            $details['view'] = $rendered['view'];
            $details['channels'] = $rendered['channels'];
            $details['formats'] = $this->formats($rendered['channels']);
        } catch (Throwable $exception) {
            $details['error'] = $exception->getMessage();
        }

        return $details;
    }

    /**
     * The bodies the preview can show: the mail message as HTML and text, plus a
     * JSON payload per other channel the notification declares and the config
     * opted in.
     *
     * @param  list<string>  $channels
     * @return list<array{value: string, label: string}>
     */
    protected function formats(array $channels): array
    {
        $enabled = array_values(array_filter($channels, fn (string $channel) => $this->renderer->isEnabled($channel)));

        $others = array_values(array_filter(
            $enabled,
            fn (string $channel) => $this->renderer->channelName($channel) !== 'Mail',
        ));

        $mail = count($others) < count($enabled);

        return [
            ...$mail ? [['value' => 'html', 'label' => 'HTML'], ['value' => 'text', 'label' => 'Text']] : [],
            ...array_map(fn (string $channel) => [
                'value' => $channel,
                'label' => $this->renderer->channelName($channel),
            ], $others),
        ];
    }

    /**
     * @param  class-string  $class
     */
    public function notifiableFor(string $class, ?string $variant = null): object
    {
        $variants = $this->registry->for($class)->resolveVariants();
        $selected = $variant !== null
            ? ($variants[$variant] ?? null)
            : ($variants === [] ? null : reset($variants));

        return $selected?->resolveNotifiable() ?? $this->registry->resolveNotifiable();
    }

    /**
     * Constructor parameters and whether the UI may edit them.
     *
     * @param  class-string  $class
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
            $value = $this->stringify($this->safeResolve($class, $parameter, $overrides));

            return [
                'name' => $parameter->getName(),
                'type' => $this->factory->namedTypeName($parameter->getType()) ?? 'mixed',
                'editable' => $editable,
                'input' => $this->factory->inputType($parameter),
                'options' => $this->factory->enumOptions($parameter),
                'value' => $editable ? $value : null,
                'preview' => $editable ? null : $value,
            ];
        }, $constructor->getParameters());
    }

    /**
     * @param  class-string  $class
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
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format('Y-m-d\TH:i'),
            is_object($value) => class_basename($value),
            is_array($value) => 'array('.count($value).')',
            default => gettype($value),
        };
    }

    protected function relativePath(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR);
    }
}
