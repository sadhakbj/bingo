<?php

declare(strict_types = 1);

namespace Bingo\RateLimit\Store;

use Bingo\RateLimit\Contracts\RateLimiterStore;
use Redis;

/**
 * Redis-backed rate limit store.
 *
 * The only store supported for production. Works correctly across all
 * workers, containers, and pods because all processes share one Redis.
 *
 * Requires the phpredis extension: https://github.com/phpredis/phpredis
 *
 * Wire via .env (automatic):
 *   RATE_LIMIT_STORE=redis
 *   REDIS_HOST=redis-service
 *   REDIS_PORT=6379
 *   REDIS_PASSWORD=secret
 *   REDIS_DB=0
 *
 * Or inject a custom connection in bootstrap/app.php:
 *   $app->instance(RateLimiterStore::class, RedisStore::fromConnection($redis));
 */
final class RedisStore implements RateLimiterStore
{
    /**
     * Atomically increment + set TTL on the first hit in a window.
     *
     * Using Lua guarantees the INCR and EXPIRE are a single atomic operation —
     * critical under concurrent requests from many pods hitting the same key.
     * TTL is 2× the window so the previous window survives for the
     * sliding-window weighted estimate.
     */
    private const string INCR_SCRIPT = <<<'LUA'
        local count = redis.call('INCR', KEYS[1])
        if count == 1 then
            redis.call('EXPIRE', KEYS[1], ARGV[1])
        end
        return count
        LUA;

    private const string PREFIX = 'bingo_rl';

    public function __construct(
        private readonly Redis $redis,
    ) {
    }

    public function increment(string $key, int $windowId, int $decaySeconds): int
    {
        return (int) $this->redis->eval(
            self::INCR_SCRIPT,
            [$this->redisKey($key, $windowId), (string) ( $decaySeconds * 2 )],
            1,
        );
    }

    public function count(string $key, int $windowId): int
    {
        $value = $this->redis->get($this->redisKey($key, $windowId));
        return $value === false ? 0 : (int) $value;
    }

    /**
     * Delete all window counters for a key (e.g. after unblocking a user).
     * Uses SCAN — never blocks the Redis server the way KEYS would.
     */
    public function reset(string $key): void
    {
        $pattern = self::PREFIX . ':' . hash('sha256', $key) . ':*';
        $cursor  = 0;

        $prev = $this->redis->getOption(Redis::OPT_SCAN);
        $this->redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);

        do {
            $keys = $this->redis->scan($cursor, $pattern, 100);
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
        } while ($cursor !== 0);

        $this->redis->setOption(Redis::OPT_SCAN, $prev);
    }

    // -------------------------------------------------------------------------
    // Factories
    // -------------------------------------------------------------------------

    /**
     * Create from individual connection params — mirrors Laravel's REDIS_* vars.
     *
     * @param string      $host     REDIS_HOST
     * @param int         $port     REDIS_PORT
     * @param string|null $password REDIS_PASSWORD (null / "null" / "" = no auth)
     * @param int         $db       REDIS_DB
     *
     * @throws \RedisException      if the connection cannot be established
     * @throws \RuntimeException    if the phpredis extension is not loaded
     */
    public static function fromConfig(
        string $host = '127.0.0.1',
        int $port = 6379,
        ?string $password = null,
        int $db = 0,
    ): self {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('The phpredis extension is required. Install it with: pecl install redis');
        }

        $redis = new Redis();
        $redis->connect($host, $port);

        if ($password !== null && $password !== '' && $password !== 'null') {
            $redis->auth($password);
        }

        if ($db !== 0) {
            $redis->select($db);
        }

        return new self($redis);
    }

    /**
     * Create from an existing \Redis connection you manage yourself.
     * Use this when you need connection pooling, sentinels, or clusters.
     */
    public static function fromConnection(Redis $redis): self
    {
        return new self($redis);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function redisKey(string $key, int $windowId): string
    {
        // Hash the raw key so colons / special chars in IPs or route names
        // cannot break the SCAN glob pattern used by reset().
        return self::PREFIX . ':' . hash('sha256', $key) . ':' . $windowId;
    }
}
