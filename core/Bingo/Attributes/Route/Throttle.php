<?php

declare(strict_types=1);

namespace Bingo\Attributes\Route;

use Attribute;

/**
 * Apply a per-route (or per-controller) rate limit.
 *
 * The limit is keyed per IP per route, so different routes have independent
 * counters even for the same client.
 *
 * Usage:
 *
 *   #[Throttle(requests: 60, per: 60)]          // 60 req / minute
 *   #[Throttle(requests: 1000, per: 3600)]       // 1 000 req / hour
 *
 * On a controller class the limit applies to every action in that controller.
 * On a method it applies only to that action. Both can coexist — each creates
 * its own independent rate-limit bucket.
 *
 * To override the storage backend, bind RateLimiterStore in bootstrap/app.php:
 *
 *   $app->instance(RateLimiterStore::class,
 *       new FileStore(base_path('storage/rate-limit')));
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Throttle
{
    public function __construct(
        /** Maximum number of requests allowed in $per seconds. */
        public int $requests,

        /** Window length in seconds. */
        public int $per,
    ) {}
}
