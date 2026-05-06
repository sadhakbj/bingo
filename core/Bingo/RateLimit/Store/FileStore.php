<?php

declare(strict_types=1);

namespace Bingo\RateLimit\Store;

use Bingo\RateLimit\Contracts\RateLimiterStore;

/**
 * File-based rate limit store.
 *
 * Each (key, window) pair is persisted as a JSON file under $directory.
 * Counts survive process restarts, making this suitable for single-server
 * production deployments without external infrastructure.
 *
 * For distributed deployments, implement RateLimiterStore with Redis or
 * another shared backend and bind it in bootstrap/app.php.
 *
 * Example:
 *   $app->instance(RateLimiterStore::class,
 *       new FileStore(base_path('storage/rate-limit')));
 */
class FileStore implements RateLimiterStore
{
    public function __construct(
        private readonly string $directory,
    ) {}

    public function increment(string $key, int $windowId, int $decaySeconds): int
    {
        $this->ensureDirectory();

        $path   = $this->path($key, $windowId);
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open rate limit store file: {$path}");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException("Unable to lock rate limit store file: {$path}");
            }

            $data = $this->readFromHandle($handle);
            $data['count']++;
            $data['expires_at'] = time() + ($decaySeconds * 2);

            $this->writeToHandle($handle, $data);

            flock($handle, LOCK_UN);

            return $data['count'];
        } finally {
            fclose($handle);
        }
    }

    public function count(string $key, int $windowId): int
    {
        $path = $this->path($key, $windowId);
        if (!file_exists($path)) {
            return 0;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return 0;
            }

            $data = $this->readFromHandle($handle);
            flock($handle, LOCK_UN);

            return $data['count'];
        } finally {
            fclose($handle);
        }
    }

    public function reset(string $key): void
    {
        $prefix = hash('sha256', $key) . '_';

        foreach (glob($this->directory . '/' . $prefix . '*.json') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function path(string $key, int $windowId): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '_' . $windowId . '.json';
    }

    private function readFromHandle($handle): array
    {
        rewind($handle);
        $raw = stream_get_contents($handle);

        if ($raw === false || $raw === '') {
            return ['count' => 0];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['count' => 0];
        }

        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            return ['count' => 0];
        }

        return $data;
    }

    private function writeToHandle($handle, array $data): void
    {
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($data));
        fflush($handle);
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, recursive: true);
        }
    }
}
