<?php

declare(strict_types=1);

namespace Bingo\Discovery\Discoverers;

use Bingo\Attributes\Provider\Bind;

/**
 * Scans app/ recursively for interfaces carrying #[Bind] and returns
 * a map of interface → [concrete, singleton] for caching.
 */
class BindingDiscoverer implements DiscovererInterface
{
    private const NAMESPACE = 'App';

    public function __construct(private readonly string $appPath) {}

    public function type(): string
    {
        return 'bindings';
    }

    public function discover(): array
    {
        $bindings = [];

        if (!is_dir($this->appPath)) {
            return $bindings;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->appPath, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace($this->appPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $fqn      = self::NAMESPACE . '\\' . str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);

            if (!interface_exists($fqn)) {
                continue;
            }

            $attrs = (new \ReflectionClass($fqn))->getAttributes(Bind::class);

            if (empty($attrs)) {
                continue;
            }

            $binding          = $attrs[0]->newInstance();
            $bindings[$fqn]   = [
                'concrete'  => $binding->concrete,
                'singleton' => $binding->singleton,
            ];
        }

        return $bindings;
    }
}