<?php

declare(strict_types = 1);

namespace Bingo\RateLimit\Contracts;

interface RateLimiterStore
{
    /**
     * Increment the counter for the given key and window, returning the new count.
     * The store may expire the entry after $decaySeconds.
     */
    public function increment(string $key, int $windowId, int $decaySeconds): int;

    /**
     * Return the current count for the given key and window (0 if absent or expired).
     */
    public function count(string $key, int $windowId): int;

    /**
     * Remove all counters associated with the given key (all windows).
     */
    public function reset(string $key): void;
}
