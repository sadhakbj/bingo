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
    public function __construct(private readonly string $directory) {}

    public function increment(string $key, int $windowId, int $decaySeconds): int
    {
        $this->ensureDirectory();

        $path = $this->path($key, $windowId);
        $data = $this->read($path);

        $data['count']++;
        $data['expires_at'] = time() + ($decaySeconds * 2);

        $this->write($path, $data);

        return $data['count'];
    }

    public function count(string $key, int $windowId): int
    {
        $data = $this->read($this->path($key, $windowId));

        return $data['count'];
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

    private function read(string $path): array
    {
        if (!file_exists($path)) {
            return ['count' => 0];
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return ['count' => 0];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['count' => 0];
        }

        // Treat expired entries as zero — do not delete here to avoid race conditions
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            return ['count' => 0];
        }

        return $data;
    }

    private function write(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data), LOCK_EX);
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, recursive: true);
        }
    }
}
