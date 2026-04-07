<?php

declare(strict_types=1);

namespace Bingo\Providers;

use Bingo\Application;
use Bingo\Attributes\Provider\ServiceProvider;
use Bingo\Attributes\Provider\Singleton;
use Bingo\RateLimit\Contracts\RateLimiterStore;
use Bingo\RateLimit\Store\FileStore;
use Bingo\RateLimit\Store\RedisStore;
use Config\RateLimitConfig;

#[ServiceProvider]
class RateLimitServiceProvider
{
    /**
     * Bind the rate limiter store based on RATE_LIMIT_DRIVER.
     *
     *   RATE_LIMIT_DRIVER=redis  → RedisStore (multi-process / distributed)
     *   RATE_LIMIT_DRIVER=file   → FileStore  (single-process, dev default)
     */
    #[Singleton]
    public function rateLimiterStore(Application $app, RateLimitConfig $config): RateLimiterStore
    {
        if ($config->driver === 'redis') {
            if (!extension_loaded('redis')) {
                throw new \RuntimeException(
                    'RATE_LIMIT_DRIVER=redis requires the ext-redis PHP extension.',
                );
            }

            $redis = new \Redis();
            $redis->connect($config->redisHost, $config->redisPort);

            if ($config->redisPassword !== 'null' && $config->redisPassword !== '') {
                $redis->auth($config->redisPassword);
            }

            $redis->select($config->redisDb);

            return RedisStore::fromConnection($redis);
        }

        return new FileStore($app->storagePath('rate-limit'));
    }
}
