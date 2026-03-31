<?php

declare(strict_types=1);

namespace Bingo\RateLimit\Store;

use Bingo\RateLimit\Contracts\RateLimiterStore;

/**
 * In-process rate limit store backed by a static PHP array.
 *
 * Counts are shared across all instances within the same worker process.
 * Suitable for development and single-worker deployments. Not distributed —
 * each worker has its own counter. For multi-worker or multi-server setups,
 * use a shared backend (FileStore, or implement RateLimiterStore with Redis).
 */
class InMemoryStore implements RateLimiterStore
{
    /** @var array<string, int> */
    private static array $counts = [];

    public function increment(string $key, int $windowId, int $decaySeconds): int
    {
        $storageKey = $this->storageKey($key, $windowId);

        self::$counts[$storageKey] = (self::$counts[$storageKey] ?? 0) + 1;

        return self::$counts[$storageKey];
    }

    public function count(string $key, int $windowId): int
    {
        return self::$counts[$this->storageKey($key, $windowId)] ?? 0;
    }

    public function reset(string $key): void
    {
        $prefix = $key . ':';
        foreach (array_keys(self::$counts) as $storageKey) {
            if (str_starts_with($storageKey, $prefix)) {
                unset(self::$counts[$storageKey]);
            }
        }
    }

    /**
     * Flush all counters. Intended for use in tests only.
     */
    public static function flush(): void
    {
        self::$counts = [];
    }

    private function storageKey(string $key, int $windowId): string
    {
        return $key . ':' . $windowId;
    }
}
