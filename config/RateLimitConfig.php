<?php

declare(strict_types=1);

namespace Config;

use Bingo\Attributes\Config\Env;

/**
 * Rate limiting configuration.
 *
 * Global limits are driven by these env vars.
 * Per-route / per-controller limits use the #[Throttle] attribute.
 *
 * Redis is the only supported store — it is the only backend that
 * works correctly across multiple processes, containers, or pods.
 */
final readonly class RateLimitConfig
{
    public function __construct(
            /** Disable entirely (e.g. staging environments). Default: on in production. */
        #[Env('RATE_LIMIT_ENABLED', default: true)]
        public bool $enabled,

            /** Maximum requests per IP within the window. */
        #[Env('RATE_LIMIT_MAX_REQUESTS', default: 1)]
        public int $maxRequests,

            /** Sliding-window length in seconds. */
        #[Env('RATE_LIMIT_WINDOW', default: 60)]
        public int $window,

        #[Env('REDIS_HOST', default: '127.0.0.1')]
        public string $redisHost,

        #[Env('REDIS_PORT', default: 6379)]
        public int $redisPort,

            /** Set to your Redis password, or leave as "null" / empty for no auth. */
        #[Env('REDIS_PASSWORD', default: 'null')]
        public string $redisPassword,

        #[Env('REDIS_DB', default: 0)]
        public int $redisDb,
    ) {
    }
}
