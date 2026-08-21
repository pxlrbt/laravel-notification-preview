<?php

declare(strict_types=1);

namespace pxlrbt\LaravelNotificationPreview;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Throwable;
use UnitEnum;

class NotificationFactory
{
    public function __construct(protected NotificationPreview $registry) {}

    /**
     * @param  class-string  $class
     * @param  array<string, mixed>  $overrides
     */
    public function make(string $class, ?string $variant = null, array $overrides = []): Notification|Mailable
    {
        $variants = $this->registry->for($class)->resolveVariants();

        if ($variant !== null && isset($variants[$variant])) {
            return $variants[$variant]->resolve();
        }

        if ($variant === null && $variants !== []) {
            return reset($variants)->resolve();
        }

        return $this->makeFromConstructor($class, $overrides);
    }

    /**
     * @param  class-string  $class
     * @param  array<string, mixed>  $overrides
     */
    protected function makeFromConstructor(string $class, array $overrides): Notification|Mailable
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        $arguments = $constructor === null ? [] : array_map(
            fn (ReflectionParameter $parameter) => $this->resolveParameter($class, $parameter, $overrides),
            $constructor->getParameters(),
        );

        $instance = $reflection->newInstanceArgs($arguments);

        if (! $instance instanceof Notification && ! $instance instanceof Mailable) {
            throw new InvalidArgumentException($class.' is neither a notification nor a mailable.');
        }

        return $instance;
    }

    /**
     * @param  class-string  $class
     * @param  array<string, mixed>  $overrides
     */
    public function resolveParameter(string $class, ReflectionParameter $parameter, array $overrides = []): mixed
    {
        $name = $parameter->getName();

        if (array_key_exists($name, $overrides) && $this->isOverridable($parameter)) {
            return $this->castOverride($parameter, $overrides[$name]);
        }

        if ($this->registry->hasResolver($class.'::$'.$name)) {
            return $this->registry->callResolver($class.'::$'.$name);
        }

        foreach ($this->parentReferences($class, $name) as $reference) {
            if ($this->registry->hasResolver($reference)) {
                return $this->registry->callResolver($reference);
            }
        }

        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && $this->registry->hasResolver($type->getName())) {
            return $this->registry->callResolver($type->getName());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if (! $unionType instanceof ReflectionNamedType || $unionType->getName() === 'null') {
                    continue;
                }

                try {
                    return $this->resolveType($unionType->getName(), $parameter);
                } catch (Throwable) {
                    continue;
                }
            }
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->resolveType($type->getName(), $parameter);
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        if ($parameter->isVariadic()) {
            return [];
        }

        return $this->fakeScalar('string', $name);
    }

    /**
     * Resolver keys registered against a parent class also apply to its children,
     * so a single registration covers a whole notification hierarchy.
     *
     * @return list<string>
     */
    protected function parentReferences(string $class, string $parameter): array
    {
        return array_map(
            fn (string $parent) => $parent.'::$'.$parameter,
            array_values(class_parents($class) ?: []),
        );
    }

    protected function resolveType(string $type, ReflectionParameter $parameter): mixed
    {
        if ($type === 'null') {
            return null;
        }

        if (in_array($type, ['int', 'integer', 'float', 'double', 'bool', 'boolean', 'string', 'array', 'mixed'], true)) {
            return $this->fakeScalar($type, $parameter->getName());
        }

        if (enum_exists($type)) {
            $cases = $type::cases();

            if ($cases !== []) {
                return $cases[0];
            }
        }

        if (is_a($type, DateTimeInterface::class, true)) {
            return is_a($type, CarbonImmutable::class, true) ? CarbonImmutable::now() : Carbon::now();
        }

        if (is_a($type, Model::class, true)) {
            return $this->makeModel($type);
        }

        if (is_a($type, EloquentCollection::class, true)) {
            return new EloquentCollection;
        }

        if (is_a($type, SupportCollection::class, true)) {
            return new SupportCollection;
        }

        try {
            return app()->make($type);
        } catch (Throwable $exception) {
            if ($parameter->allowsNull()) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @param  class-string<Model>  $class
     */
    public function makeModel(string $class): ?Model
    {
        if (method_exists($class, 'factory')) {
            try {
                /** @var Model */
                return $class::factory()->make();
            } catch (Throwable) {
                //
            }
        }

        try {
            if (($model = $class::query()->first()) !== null) {
                return $model;
            }
        } catch (Throwable) {
            //
        }

        try {
            return new $class;
        } catch (Throwable) {
            return null;
        }
    }

    protected function fakeScalar(string $type, string $name): mixed
    {
        return match ($type) {
            'int', 'integer' => 42,
            'float', 'double' => 42.0,
            'bool', 'boolean' => true,
            'array' => [],
            default => $this->fakeString($name),
        };
    }

    protected function fakeString(string $name): string
    {
        $name = Str::lower($name);

        return match (true) {
            str_contains($name, 'email') => 'preview@example.com',
            str_contains($name, 'url'), str_contains($name, 'link') => 'https://example.com',
            str_contains($name, 'name') => 'Jane Doe',
            str_ends_with($name, 'id') => 'ORD-1001',
            default => 'Sample text',
        };
    }

    /**
     * Only values that can round-trip through a query string may be edited in
     * the preview's UI.
     */
    public function isOverridable(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        if ($type === null) {
            return true;
        }

        $name = $this->namedTypeName($type);

        if ($name === null) {
            return false;
        }

        return in_array($name, ['int', 'integer', 'float', 'double', 'bool', 'boolean', 'string'], true)
            || is_a($name, DateTimeInterface::class, true)
            || enum_exists($name);
    }

    /**
     * The input type the UI should render for this parameter.
     */
    public function inputType(ReflectionParameter $parameter): string
    {
        $name = $this->namedTypeName($parameter->getType());

        if ($name !== null && enum_exists($name)) {
            return 'select';
        }

        if ($name !== null && is_a($name, DateTimeInterface::class, true)) {
            return 'datetime-local';
        }

        return match ($name) {
            'int', 'integer', 'float', 'double' => 'number',
            'bool', 'boolean' => 'checkbox',
            default => 'text',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function enumOptions(ReflectionParameter $parameter): array
    {
        $name = $this->namedTypeName($parameter->getType());

        if ($name === null || ! enum_exists($name)) {
            return [];
        }

        return array_map(fn (UnitEnum $case) => [
            'value' => $case instanceof BackedEnum ? (string) $case->value : $case->name,
            'label' => $case->name,
        ], $name::cases());
    }

    public function namedTypeName(?ReflectionType $type): ?string
    {
        if ($type instanceof ReflectionNamedType && $type->getName() !== 'null') {
            return $type->getName();
        }

        if ($type instanceof ReflectionUnionType) {
            $names = array_values(array_unique(array_filter(array_map(
                fn (ReflectionType $unionType) => $unionType instanceof ReflectionNamedType && $unionType->getName() !== 'null'
                    ? $unionType->getName()
                    : null,
                $type->getTypes(),
            ))));

            if (count($names) === 1) {
                return $names[0];
            }
        }

        return null;
    }

    protected function castOverride(ReflectionParameter $parameter, mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            if ($parameter->allowsNull()) {
                return null;
            }

            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
        }

        $type = $this->namedTypeName($parameter->getType());

        if ($type !== null && enum_exists($type)) {
            return is_a($type, BackedEnum::class, true)
                ? $type::tryFrom($value) ?? $type::cases()[0]
                : constant($type.'::'.$value);
        }

        if ($type !== null && is_a($type, DateTimeInterface::class, true)) {
            return Carbon::parse(is_scalar($value) ? (string) $value : null);
        }

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value === null ? '' : (string) $value,
        };
    }
}
