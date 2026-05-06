<?php

declare(strict_types = 1);

namespace Bingo\RateLimit;

use Bingo\RateLimit\Contracts\RateLimiterStore;

/**
 * Rate limiter using a sliding-window counter algorithm.
 *
 * The sliding window counter approximation weights the previous window's count
 * by how much of the current window has elapsed. This eliminates the burst
 * problem of the fixed-window algorithm while remaining O(1) in space per key.
 *
 * Estimated rate = prev_count × (1 − elapsed_fraction) + curr_count
 *
 * If estimated_rate ≥ limit the request is denied; otherwise the current
 * window counter is incremented and the request is allowed.
 */
readonly class RateLimiter
{
    public function __construct(
        private RateLimiterStore $store,
    ) {
    }

    /**
     * Record a hit and return the result.
     *
     * @param string $key           Unique identifier for the rate-limit bucket (e.g. IP, user ID, route+IP)
     * @param int    $limit         Maximum number of requests allowed in $windowSeconds
     * @param int    $windowSeconds Length of the sliding window in seconds
     */
    public function attempt(string $key, int $limit, int $windowSeconds): RateLimitResult
    {
        $now          = time();
        $currentWinId = (int) floor($now / $windowSeconds);
        $prevWinId    = $currentWinId - 1;

        $prevCount = $this->store->count($key, $prevWinId);
        $currCount = $this->store->count($key, $currentWinId);

        // How far we are through the current window (0.0 → 1.0)
        $elapsedFraction = ( $now - ( $currentWinId * $windowSeconds ) ) / $windowSeconds;

        // Weighted estimate: previous window contributes less as we move through current window
        $estimated = (int) floor(( $prevCount * ( 1.0 - $elapsedFraction ) ) + $currCount);

        $resetAt = ( $currentWinId + 1 ) * $windowSeconds;

        if ($estimated >= $limit) {
            return new RateLimitResult(
                allowed   : false,
                limit     : $limit,
                remaining : 0,
                resetAt   : $resetAt,
                retryAfter: max(1, $resetAt - $now),
            );
        }

        $newCurrCount = $this->store->increment($key, $currentWinId, $windowSeconds * 2);

        $newEstimated = (int) floor(( $prevCount * ( 1.0 - $elapsedFraction ) ) + $newCurrCount);
        $remaining    = max(0, $limit - $newEstimated);

        return new RateLimitResult(
            allowed   : true,
            limit     : $limit,
            remaining : $remaining,
            resetAt   : $resetAt,
            retryAfter: 0,
        );
    }

    /**
     * Return true if the key has exceeded the limit without recording a hit.
     */
    public function tooManyAttempts(string $key, int $limit, int $windowSeconds): bool
    {
        $now          = time();
        $currentWinId = (int) floor($now / $windowSeconds);
        $prevWinId    = $currentWinId - 1;

        $prevCount       = $this->store->count($key, $prevWinId);
        $currCount       = $this->store->count($key, $currentWinId);
        $elapsedFraction = ( $now - ( $currentWinId * $windowSeconds ) ) / $windowSeconds;

        return (int) floor(( $prevCount * ( 1.0 - $elapsedFraction ) ) + $currCount) >= $limit;
    }

    /**
     * Clear all counters for the given key (e.g. after successful verification).
     */
    public function clear(string $key): void
    {
        $this->store->reset($key);
    }
}
