<?php

declare(strict_types=1);

namespace Bingo\Discovery\Discoverers;

use Bingo\Attributes\Provider\ServiceProvider as ServiceProviderAttribute;

/**
 * Scans core/Bingo/Providers/ and app/Providers/ for classes carrying
 * #[ServiceProvider] and returns an ordered list of FQCNs for caching.
 * Core providers are always listed before app providers.
 */
class ProviderDiscoverer implements DiscovererInterface
{
    public function __construct(
        private readonly string $coreBingoPath,
        private readonly string $appPath,
    ) {}

    public function type(): string
    {
        return 'providers';
    }

    public function discover(): array
    {
        $providers = [];

        $paths = [
            [$this->coreBingoPath . DIRECTORY_SEPARATOR . 'Providers', 'Bingo\\Providers'],
            [$this->appPath . DIRECTORY_SEPARATOR . 'Providers', 'App\\Providers'],
        ];

        foreach ($paths as [$path, $namespace]) {
            if (!is_dir($path)) {
                continue;
            }

            foreach (glob($path . '/*.php') as $file) {
                $className = $namespace . '\\' . basename($file, '.php');

                if (!class_exists($className)) {
                    continue;
                }

                $attrs = new \ReflectionClass($className)->getAttributes(ServiceProviderAttribute::class);

                if (empty($attrs)) {
                    continue;
                }

                $providers[] = $className;
            }
        }

        return $providers;
    }
}
