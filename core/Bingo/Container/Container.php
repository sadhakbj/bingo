<?php

declare(strict_types = 1);

namespace Bingo\Container;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class Container implements ContainerInterface
{
    private ContainerBuilder $builder;
    private bool $isCompiled = false;

    /** Pre-built instances — always win, bypass Symfony entirely. */
    private array $instances = [];

    /** IDs registered via singleton() or bind() — used to route to Symfony. */
    private array $bindings = [];

    /** Circular dependency detection stack. */
    private array $resolving = [];

    public function __construct()
    {
        $this->builder = new ContainerBuilder();
    }

    // -------------------------------------------------------------------------
    // Registration API
    // -------------------------------------------------------------------------

    /**
     * Register a singleton — one shared instance for the entire lifecycle.
     *
     *   $app->singleton(UserService::class);
     *   $app->singleton(CacheInterface::class, RedisCache::class);
     */
    public function singleton(string $abstract, ?string $concrete = null): void
    {
        $this->assertNotCompiled('singleton');

        $definition = new Definition($concrete ?? $abstract);
        $definition->setShared(true);
        $definition->setAutowired(true);
        $definition->setPublic(true); // required to keep service accessible after compile

        $this->builder->setDefinition($abstract, $definition);
        $this->bindings[$abstract] = true;
    }

    /**
     * Register a transient binding — new instance on every resolution.
     *
     *   $app->bind(MailerInterface::class, SmtpMailer::class);
     */
    public function bind(string $abstract, ?string $concrete = null): void
    {
        $this->assertNotCompiled('bind');

        $definition = new Definition($concrete ?? $abstract);
        $definition->setShared(false);
        $definition->setAutowired(true);
        $definition->setPublic(true); // required to keep service accessible after compile

        $this->builder->setDefinition($abstract, $definition);
        $this->bindings[$abstract] = true;
    }

    /**
     * Register a pre-built object instance.
     * Always acts as a singleton. Bypasses Symfony DI entirely.
     *
     *   $app->instance(Config::class, new Config([...]));
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    // -------------------------------------------------------------------------
    // PSR-11
    // -------------------------------------------------------------------------

    /**
     * Resolve an entry from the container.
     *
     * Resolution order:
     *   1. Pre-built instances (instance())           — always win
     *   2. Symfony compiled container                 — singleton() / bind()
     *   3. Reflection fallback                        — zero-config autowiring
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            $this->ensureCompiled();
            return $this->builder->get($id);
        }

        return $this->resolveViaReflection($id);
    }

    /**
     * Returns true if the container can provide an entry for the given id.
     */
    public function has(string $id): bool
    {
        if (isset($this->instances[$id]) || isset($this->bindings[$id])) {
            return true;
        }

        // Reflection can resolve any existing, non-abstract concrete class.
        return class_exists($id) && !new ReflectionClass($id)->isAbstract();
    }

    /**
     * Resolve a class from the container — alias for get().
     */
    public function make(string $id): mixed
    {
        return $this->get($id);
    }

    // -------------------------------------------------------------------------
    // Compilation
    // -------------------------------------------------------------------------

    /**
     * Compile the Symfony ContainerBuilder. Idempotent.
     * Called automatically on first Symfony resolution, and explicitly in Application::run().
     */
    public function compile(): void
    {
        if ($this->isCompiled) {
            return;
        }

        $this->builder->compile();
        $this->isCompiled = true;
    }

    private function ensureCompiled(): void
    {
        if (!$this->isCompiled) {
            $this->compile();
        }
    }

    // -------------------------------------------------------------------------
    // Reflection fallback — zero-config autowiring for unregistered classes
    // -------------------------------------------------------------------------

    private function resolveViaReflection(string $class): object
    {
        if (!class_exists($class)) {
            throw new NotFoundException($class);
        }

        if (isset($this->resolving[$class])) {
            $chain = implode(' → ', array_keys($this->resolving)) . ' → ' . $class;
            throw new ContainerException("Circular dependency detected while resolving '{$class}': {$chain}");
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || $reflection->isInterface()) {
            throw new NotFoundException($class);
        }

        $this->resolving[$class] = true;

        try {
            $constructor = $reflection->getConstructor();

            if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
                return $reflection->newInstanceArgs([]);
            }

            $args = [];
            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();

                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    // Try to resolve the typed dependency.
                    // If it fails and the param has a default/is nullable, fall back gracefully.
                    try {
                        $args[] = $this->get($type->getName());
                    } catch (NotFoundException $e) {
                        if ($param->isOptional()) {
                            $args[] = $param->getDefaultValue();
                        } elseif ($param->allowsNull()) {
                            $args[] = null;
                        } else {
                            throw new ContainerException(
                                "Cannot resolve '{$type->getName()}' for parameter "
                                . "'\${$param->getName()}' in '{$class}'. "
                                . 'Register it explicitly via singleton() or bind().',
                                0,
                                $e,
                            );
                        }
                    }
                } elseif ($param->isOptional()) {
                    $args[] = $param->getDefaultValue();
                } elseif ($param->allowsNull()) {
                    $args[] = null;
                } else {
                    throw new ContainerException(
                        "Cannot auto-resolve parameter '\${$param->getName()}' "
                        . '(type: '
                        . ( $type?->getName() ?? 'mixed' )
                        . ') '
                        . "in class '{$class}'. Register it explicitly via singleton() or bind().",
                    );
                }
            }

            return $reflection->newInstanceArgs($args);
        } finally {
            unset($this->resolving[$class]);
        }
    }

    private function assertNotCompiled(string $method): void
    {
        if ($this->isCompiled) {
            throw new ContainerException(
                "Cannot call {$method}() after the container has been compiled. "
                . "Register all services before calling \$app->run().",
            );
        }
    }
}
