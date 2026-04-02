<?php

declare(strict_types=1);

namespace Bingo\Providers;

use Bingo\Attributes\Provider\ServiceProvider;
use Bingo\Attributes\Provider\Singleton;
use Bingo\RateLimit\Contracts\RateLimiterStore;
use Bingo\RateLimit\Store\FileStore;

#[ServiceProvider]
class RateLimitServiceProvider
{
    /**
     * Default store is FileStore (dev-safe, persistent across requests).
     * Override in app/Providers/ with RedisStore for production.
     */
    #[Singleton]
    public function rateLimiterStore(): RateLimiterStore
    {
        return new FileStore(base_path('storage/rate-limit'));
    }
}