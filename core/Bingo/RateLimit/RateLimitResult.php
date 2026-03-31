<?php

declare(strict_types=1);

namespace Bingo\RateLimit;

/**
 * Immutable result of a single rate-limit check.
 */
final readonly class RateLimitResult
{
    public function __construct(
        /** Whether the request is within the allowed limit. */
        public bool $allowed,

        /** The configured maximum number of requests in the window. */
        public int $limit,

        /** Estimated requests remaining in the current window (0 when denied). */
        public int $remaining,

        /** Unix timestamp at which the current window resets. */
        public int $resetAt,

        /**
         * Seconds the client should wait before retrying.
         * 0 when the request is allowed.
         * Sent as the Retry-After header on 429 responses (RFC 6585).
         */
        public int $retryAfter,
    ) {}

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function isDenied(): bool
    {
        return !$this->allowed;
    }
}
