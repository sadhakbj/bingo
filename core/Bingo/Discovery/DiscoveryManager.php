<?php

declare(strict_types=1);

namespace Bingo\Discovery;

use Bingo\Discovery\Discoverers\BindingDiscoverer;
use Bingo\Discovery\Discoverers\CommandDiscoverer;
use Bingo\Discovery\Discoverers\ControllerDiscoverer;
use Bingo\Discovery\Discoverers\DiscovererInterface;
use Bingo\Discovery\Discoverers\ProviderDiscoverer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Orchestrates the discovery process and manages caching.
 *
 * Cache layout: storage/framework/discovery/{type}.php — one file per discoverer.
 * Freshness is determined by comparing the meta.php mtime against app/ file mtimes.
 *
 * In development, rebuilds automatically when files change.
 * In production, requires pre-built cache (fail-fast if missing).
 */
class DiscoveryManager
{
    private const META_FILE = 'meta.php';

    /** @var DiscovererInterface[] */
    private array $discoverers = [];

    public function __construct(
        private readonly string $cacheDir,
        private readonly string $appPath,
        private readonly string $coreBingoPath,
        private readonly bool $isProduction,
    ) {
        $this->discoverers = [
            new ControllerDiscoverer($appPath),
            new CommandDiscoverer($appPath),
            new BindingDiscoverer($appPath),
            new ProviderDiscoverer($coreBingoPath, $appPath),
        ];
    }

    /**
     * Load discovered metadata from cache or rebuild if needed.
     *
     * @throws \RuntimeException if cache missing in production
     */
    public function load(): array
    {
        if ($this->isProduction) {
            if (!file_exists($this->metaPath())) {
                throw new \RuntimeException(
                    'Discovery cache not found. Run: php bin/bingo discovery:generate',
                );
            }
            return $this->loadFromCache();
        }

        if ($this->isCacheValid()) {
            return $this->loadFromCache();
        }

        return $this->rebuild();
    }

    /**
     * Force rebuild of discovery cache. Runs all discoverers and writes one file per type.
     */
    public function rebuild(): array
    {
        $discovered = [];

        foreach ($this->discoverers as $discoverer) {
            $type              = $discoverer->type();
            $discovered[$type] = $discoverer->discover();
            $this->writeCacheFile($type, $discovered[$type]);
        }

        $meta = [
            'generated_at' => time(),
            'environment'  => $this->isProduction ? 'production' : 'development',
        ];
        $this->writeCacheFile('meta', $meta);
        $discovered['meta'] = $meta;

        return $discovered;
    }

    /**
     * Return the cache directory path.
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * Load all cached types by requiring each file in the cache directory.
     */
    private function loadFromCache(): array
    {
        $discovered = [];

        foreach ($this->discoverers as $discoverer) {
            $type = $discoverer->type();
            $path = $this->filePath($type);
            $discovered[$type] = file_exists($path) ? require $path : [];
        }

        $discovered['meta'] = file_exists($this->metaPath()) ? require $this->metaPath() : [];

        return $discovered;
    }

    /**
     * Cache is valid when meta.php exists and no app/ file is newer than it.
     */
    private function isCacheValid(): bool
    {
        $metaPath = $this->metaPath();

        if (!file_exists($metaPath)) {
            return false;
        }

        $cacheTime = filemtime($metaPath);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->appPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && $file->getMTime() > $cacheTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * Write a single type array to its own cache file using short array syntax.
     */
    private function writeCacheFile(string $type, array $data): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, recursive: true);
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\n"
            . "// Auto-generated — DO NOT EDIT. Regenerate: php bin/bingo discovery:generate\n\n"
            . 'return ' . $this->exportArray($data) . ";\n";

        file_put_contents($this->filePath($type), $content, LOCK_EX);
    }

    private function filePath(string $type): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . $type . '.php';
    }

    private function metaPath(): string
    {
        return $this->filePath(self::META_FILE);
    }

    /**
     * Recursive short-array exporter — produces [] syntax instead of array().
     */
    private function exportArray(array $data, int $depth = 0): string
    {
        if (empty($data)) {
            return '[]';
        }

        $pad   = str_repeat('    ', $depth);
        $inner = str_repeat('    ', $depth + 1);
        $isList = array_is_list($data);
        $lines  = [];

        foreach ($data as $key => $value) {
            $exportedValue = is_array($value)
                ? $this->exportArray($value, $depth + 1)
                : var_export($value, true);

            $lines[] = $isList
                ? "{$inner}{$exportedValue},"
                : "{$inner}" . var_export($key, true) . " => {$exportedValue},";
        }

        return "[\n" . implode("\n", $lines) . "\n{$pad}]";
    }
}