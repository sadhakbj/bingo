<?php

declare(strict_types=1);

namespace Bingo\Discovery;

use Bingo\Discovery\Discoverers\CommandDiscoverer;
use Bingo\Discovery\Discoverers\ControllerDiscoverer;
use Bingo\Discovery\Discoverers\DiscovererInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Orchestrates the discovery process and manages caching.
 *
 * In development, validates cache freshness by checking file modification times.
 * In production, requires pre-built cache (fail-fast if missing).
 */
class DiscoveryManager
{
    /** @var DiscovererInterface[] */
    private array $discoverers = [];

    public function __construct(
        private readonly string $cachePath,
        private readonly string $appPath,
        private readonly bool $isProduction,
    ) {
        // Register discoverers
        $this->discoverers = [
            new ControllerDiscoverer($appPath),
            new CommandDiscoverer($appPath),
        ];
    }

    /**
     * Load discovered metadata from cache or rebuild if needed.
     *
     * @throws \RuntimeException if cache missing in production
     */
    public function load(): array
    {
        // Production: Cache must exist (fail-fast)
        if ($this->isProduction) {
            if (!file_exists($this->cachePath)) {
                throw new \RuntimeException(
                    'Discovery cache not found. Run: php bin/bingo discovery:generate',
                );
            }
            return require $this->cachePath;
        }

        // Development: Use cache if valid, rebuild if stale
        if ($this->isCacheValid()) {
            return require $this->cachePath;
        }

        return $this->rebuild();
    }

    /**
     * Force rebuild of discovery cache.
     *
     * Runs all discoverers and writes results to cache file.
     */
    public function rebuild(): array
    {
        $discovered = [];

        foreach ($this->discoverers as $discoverer) {
            $type = $discoverer->type();
            $discovered[$type] = $discoverer->discover();
        }

        // Add metadata
        $discovered['meta'] = [
            'generated_at' => time(),
            'environment' => $this->isProduction ? 'production' : 'development',
        ];

        $this->writeCache($discovered);

        return $discovered;
    }

    /**
     * Check if cache is valid (exists and no files changed since generation).
     */
    private function isCacheValid(): bool
    {
        if (!file_exists($this->cachePath)) {
            return false;
        }

        $cacheTime = filemtime($this->cachePath);

        // Check if any app file changed since cache generation
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->appPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                if ($file->getMTime() > $cacheTime) {
                    return false; // File changed, cache invalid
                }
            }
        }

        return true;
    }

    /**
     * Write discovery data to cache file.
     */
    private function writeCache(array $data): void
    {
        $cacheDir = dirname($this->cachePath);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, recursive: true);
        }

        $export = var_export($data, true);
        $content = "<?php\n\ndeclare(strict_types=1);\n\n// Auto-generated discovery cache\n// DO NOT EDIT - regenerate with: php bin/bingo discovery:generate\n\nreturn {$export};\n";

        file_put_contents($this->cachePath, $content, LOCK_EX);
    }
}
