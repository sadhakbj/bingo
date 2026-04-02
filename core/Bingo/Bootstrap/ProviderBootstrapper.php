<?php

declare(strict_types=1);

namespace Bingo\Bootstrap;

use Bingo\Attributes\Provider\Boots;
use Bingo\Attributes\Provider\Singleton;
use Bingo\Container\Container;

/**
 * Wires discovered bindings and service providers into the container.
 *
 * Accepts pre-discovered data from the DiscoveryManager cache — no filesystem
 * scanning happens here. In production the data comes from the pre-built cache;
 * in development it is rebuilt automatically when files change.
 */
readonly class ProviderBootstrapper
{
    /**
     * @param array $bindings  ['Interface\\FQN' => ['concrete' => 'Concrete\\FQN', 'singleton' => bool]]
     * @param array $providers ['Provider\\FQN', ...]  ordered: core providers first, then app providers
     */
    public function __construct(
        private Container $container,
        private array $bindings,
        private array $providers,
    ) {}

    public function boot(): void
    {
        $this->registerBindings();

        $reflected = $this->reflectProviders();
        $this->runRegisterPhase($reflected);
        $this->runBootPhase($reflected);
    }

    /**
     * Register interface → concrete bindings from the discovery cache.
     */
    private function registerBindings(): void
    {
        foreach ($this->bindings as $interface => $binding) {
            if ($binding['singleton']) {
                $this->container->singleton($interface, $binding['concrete']);
            } else {
                $this->container->bind($interface, $binding['concrete']);
            }
        }
    }

    /**
     * Build provider instances and their ReflectionClass, instantiating each class once.
     *
     * @return array<array{instance: object, reflection: \ReflectionClass}>
     */
    private function reflectProviders(): array
    {
        $reflected = [];

        foreach ($this->providers as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $reflected[] = [
                'instance'   => new $className(),
                'reflection' => new \ReflectionClass($className),
            ];
        }

        return $reflected;
    }

    /**
     * Register phase: run all #[Singleton] methods across all providers.
     * Each return value is registered immediately so subsequent providers can inject it.
     */
    private function runRegisterPhase(array $providers): void
    {
        foreach ($providers as $providerData) {
            foreach ($providerData['reflection']->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (empty($method->getAttributes(Singleton::class))) {
                    continue;
                }

                $returnType = $method->getReturnType();

                if (!$returnType instanceof \ReflectionNamedType || $returnType->getName() === 'void') {
                    continue;
                }

                $result = $method->invoke($providerData['instance'], ...$this->resolveArgs($method));
                $this->container->instance($returnType->getName(), $result);
            }
        }
    }

    /**
     * Boot phase: run all #[Boots] methods after all services are registered.
     */
    private function runBootPhase(array $providers): void
    {
        foreach ($providers as $providerData) {
            foreach ($providerData['reflection']->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (empty($method->getAttributes(Boots::class))) {
                    continue;
                }

                $method->invoke($providerData['instance'], ...$this->resolveArgs($method));
            }
        }
    }

    /**
     * Resolve method parameters from the container.
     */
    private function resolveArgs(\ReflectionMethod $method): array
    {
        $args = [];

        foreach ($method->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->container->make($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }

        return $args;
    }
}