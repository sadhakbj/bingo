<?php

declare(strict_types=1);

namespace Bingo\Config;

use Bingo\Attributes\Config\Env;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use RuntimeException;

/**
 * Instantiates a typed config class by reading #[Env] attributes and
 * resolving values from the environment.
 *
 * Two loading modes are selected automatically:
 *
 *   Constructor-based (default)
 *     Used when the class has a constructor with parameters.
 *     Reads #[Env] from constructor params (supports constructor promotion),
 *     resolves env values, then calls new $class(...$args).
 *
 *   Property-based
 *     Used when the class has no constructor or an empty constructor.
 *     Creates an instance via newInstanceWithoutConstructor() so PHP-level
 *     property defaults are preserved, then sets each property that carries
 *     an #[Env] attribute from the environment.
 */
final class ConfigLoader
{
    /**
     * Build an instance of $class by wiring #[Env] attributes to env vars.
     *
     * @template T of object
     * @param class-string<T> $class
     *
     * @return T
     * @throws \ReflectionException
     */
    public static function load(string $class): object
    {
        $reflection  = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        // Property-based mode: no constructor, or constructor with no params.
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return self::loadFromProperties($reflection);
        }

        // Constructor-based mode.
        return self::loadFromConstructor($reflection, $constructor);
    }

    // -------------------------------------------------------------------------
    // Constructor-based loading
    // -------------------------------------------------------------------------

    private static function loadFromConstructor(
        ReflectionClass $reflection,
        \ReflectionMethod $constructor,
    ): object {
        $class = $reflection->getName();
        $args  = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            // #[Env] on the param (promoted) or the underlying property.
            $attrs = $param->getAttributes(Env::class);

            if (empty($attrs) && $reflection->hasProperty($name)) {
                $attrs = $reflection->getProperty($name)->getAttributes(Env::class);
            }

            if (!empty($attrs)) {
                /** @var Env $env */
                $env  = $attrs[0]->newInstance();
                $raw  = env($env->key, $env->default);
                $type = $param->getType();

                if ($raw === null && $type instanceof ReflectionNamedType && !$type->allowsNull()) {
                    throw new RuntimeException(
                        "ConfigLoader: env var '{$env->key}' is not set and '{$class}::\${$name}' "
                        . 'is non-nullable. Set the env var or add a default value to #[Env].',
                    );
                }

                $args[$name] = self::cast($raw, $type);
                continue;
            }

            // No #[Env] — use PHP default value if available.
            if ($param->isDefaultValueAvailable()) {
                $args[$name] = $param->getDefaultValue();
                continue;
            }

            // Nullable with no default → null.
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
                $args[$name] = null;
                continue;
            }

            throw new RuntimeException(
                "ConfigLoader: cannot resolve parameter '\${$name}' of {$class}. "
                . "Add #[Env('KEY')] or provide a default value.",
            );
        }

        return $reflection->newInstance(...$args);
    }

    // -------------------------------------------------------------------------
    // Property-based loading
    // -------------------------------------------------------------------------

    private static function loadFromProperties(ReflectionClass $reflection): object
    {
        $class    = $reflection->getName();
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC
        | ReflectionProperty::IS_PROTECTED) as $prop) {
            $attrs = $prop->getAttributes(Env::class);

            if (empty($attrs)) {
                // No #[Env] — leave the PHP-level default in place (already applied
                // by newInstanceWithoutConstructor for regular property initializers).
                continue;
            }

            /** @var Env $env */
            $env  = $attrs[0]->newInstance();
            $raw  = env($env->key, $env->default);
            $type = $prop->getType();
            $name = $prop->getName();

            if ($raw === null && $type instanceof ReflectionNamedType && !$type->allowsNull()) {
                throw new RuntimeException(
                    "ConfigLoader: env var '{$env->key}' is not set and '{$class}::\${$name}' "
                    . 'is non-nullable. Set the env var or add a default value to #[Env].',
                );
            }

            $prop->setValue($instance, self::cast($raw, $type));
        }

        return $instance;
    }

    // -------------------------------------------------------------------------
    // Type casting
    // -------------------------------------------------------------------------

    private static function cast(mixed $value, ?ReflectionType $type): mixed
    {
        if ($value === null || $type === null) {
            return $value;
        }

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        if ($type->allowsNull() && $value === null) {
            return null;
        }

        return match ($type->getName()) {
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int'    => (int) $value,
            'float'  => (float) $value,
            'string' => (string) $value,
            default  => $value,
        };
    }
}
