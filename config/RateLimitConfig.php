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
 * RATE_LIMIT_DRIVER=redis  → RedisStore (requires ext-redis; works across processes/pods)
 * RATE_LIMIT_DRIVER=file   → FileStore  (single-process only; good for local dev)
 */
final readonly class RateLimitConfig
{
    public function __construct(
        /** Disable entirely (e.g. staging environments). Default: on in production. */
        #[Env('RATE_LIMIT_ENABLED', default: true)]
        public bool $enabled,

        /** "redis" or "file". Defaults to "file" so dev works with zero setup. */
        #[Env('RATE_LIMIT_DRIVER', default: 'file')]
        public string $driver,

        /** Maximum requests per IP within the window. */
        #[Env('RATE_LIMIT_REQUESTS', default: 100)]
        public int $maxRequests,

        /** Sliding-window length in seconds. */
        #[Env('RATE_LIMIT_WINDOW', default: 60)]
        public int $window,

        #[Env('REDIS_HOST', default: '127.0.0.1')]
        public string $redisHost,

        #[Env('REDIS_PORT', default: 6379)]
        public int $redisPort,

        /** Set to your Redis password, or leave as "null" / empty for no auth. */
        #[Env('REDIS_PASSWORD', default: null)]
        public ?string $redisPassword,

        #[Env('REDIS_DB', default: 0)]
        public int $redisDb,
    ) {}
}
