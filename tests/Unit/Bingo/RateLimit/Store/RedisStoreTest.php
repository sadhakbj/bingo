<?php

declare(strict_types = 1);

namespace Tests\Unit\Bingo\RateLimit\Store;

use Bingo\RateLimit\Store\RedisStore;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * Integration tests for RedisStore.
 *
 * Skipped automatically when:
 *   - the phpredis extension is not loaded, or
 *   - a Redis server is not reachable at 127.0.0.1:6379.
 *
 * Run a local Redis with: docker run -p 6379:6379 redis:7-alpine
 */
class RedisStoreTest extends TestCase
{
    private Redis $redis;
    private RedisStore $store;
    private string $prefix = 'test_bingo_rl';

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('phpredis extension not loaded.');
        }

        try {
            $this->redis = new Redis();
            $this->redis->connect('127.0.0.1', 6379, timeout: 1.0);
            $this->redis->ping();
        } catch (\RedisException $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }

        $this->store = RedisStore::fromConnection($this->redis, $this->prefix);

        // Clean up any leftover test keys before each test.
        $this->flushTestKeys();
    }

    protected function tearDown(): void
    {
        if (isset($this->redis)) {
            $this->flushTestKeys();
        }
    }

    // -------------------------------------------------------------------------
    // increment()
    // -------------------------------------------------------------------------

    public function test_increment_returns_one_on_first_hit(): void
    {
        $count = $this->store->increment('test:ip1', 1000, 60);
        $this->assertSame(1, $count);
    }

    public function test_increment_accumulates_within_the_same_window(): void
    {
        $this->store->increment('test:ip2', 1000, 60);
        $this->store->increment('test:ip2', 1000, 60);
        $count = $this->store->increment('test:ip2', 1000, 60);
        $this->assertSame(3, $count);
    }

    public function test_increment_is_isolated_per_window(): void
    {
        $this->store->increment('test:ip3', 1000, 60);
        $this->store->increment('test:ip3', 1001, 60); // different window
        $this->assertSame(1, $this->store->count('test:ip3', 1000));
        $this->assertSame(1, $this->store->count('test:ip3', 1001));
    }

    public function test_increment_sets_ttl_on_first_hit(): void
    {
        $this->store->increment('test:ip4', 2000, 60);

        // Key should have a TTL ≤ 120 (2 × 60) and > 0
        $key = $this->prefix . ':' . hash('sha256', 'test:ip4') . ':2000';
        $ttl = $this->redis->ttl($key);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(120, $ttl);
    }

    public function test_subsequent_increments_do_not_reset_ttl(): void
    {
        $key = $this->prefix . ':' . hash('sha256', 'test:ip5') . ':3000';

        $this->store->increment('test:ip5', 3000, 60);
        $ttlAfterFirst = $this->redis->ttl($key);

        // Tiny sleep so TTL has ticked down
        usleep(50_000); // 50 ms

        $this->store->increment('test:ip5', 3000, 60);
        $ttlAfterSecond = $this->redis->ttl($key);

        // TTL should be lower (or equal — Redis resolution is 1 second)
        $this->assertLessThanOrEqual($ttlAfterFirst, $ttlAfterSecond);
    }

    // -------------------------------------------------------------------------
    // count()
    // -------------------------------------------------------------------------

    public function test_count_returns_zero_for_unknown_key(): void
    {
        $this->assertSame(0, $this->store->count('nonexistent:key', 9999));
    }

    public function test_count_reflects_increments(): void
    {
        $this->store->increment('test:ip6', 4000, 60);
        $this->store->increment('test:ip6', 4000, 60);
        $this->assertSame(2, $this->store->count('test:ip6', 4000));
    }

    // -------------------------------------------------------------------------
    // reset()
    // -------------------------------------------------------------------------

    public function test_reset_clears_all_windows_for_key(): void
    {
        $this->store->increment('test:ip7', 5000, 60);
        $this->store->increment('test:ip7', 5001, 60);

        $this->store->reset('test:ip7');

        $this->assertSame(0, $this->store->count('test:ip7', 5000));
        $this->assertSame(0, $this->store->count('test:ip7', 5001));
    }

    public function test_reset_does_not_affect_other_keys(): void
    {
        $this->store->increment('test:ip8', 6000, 60);
        $this->store->increment('test:ip8alt', 6000, 60);

        $this->store->reset('test:ip8');

        $this->assertSame(0, $this->store->count('test:ip8', 6000));
        $this->assertSame(1, $this->store->count('test:ip8alt', 6000));
    }

    // -------------------------------------------------------------------------
    // fromConfig() factory
    // -------------------------------------------------------------------------

    public function test_from_config_connects_with_defaults(): void
    {
        $store = RedisStore::fromConfig(host: '127.0.0.1', port: 6379);
        $this->assertInstanceOf(RedisStore::class, $store);

        $count = $store->increment('test:fromConfig', 7000, 60);
        $this->assertSame(1, $count);
        $store->reset('test:fromConfig');
    }

    public function test_from_config_ignores_null_string_password(): void
    {
        // "null" is the default sentinel value in .env — must not be passed to auth()
        $store = RedisStore::fromConfig(host: '127.0.0.1', port: 6379, password: 'null');
        $this->assertInstanceOf(RedisStore::class, $store);

        $count = $store->increment('test:nullPassword', 8000, 60);
        $this->assertSame(1, $count);
        $store->reset('test:nullPassword');
    }

    public function test_from_config_selects_database(): void
    {
        $store = RedisStore::fromConfig(host: '127.0.0.1', port: 6379, db: 1);
        $this->assertInstanceOf(RedisStore::class, $store);

        $count = $store->increment('test:db1', 9000, 60);
        $this->assertSame(1, $count);
        $store->reset('test:db1');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function flushTestKeys(): void
    {
        $cursor = 0;
        $prev   = $this->redis->getOption(Redis::OPT_SCAN);
        $this->redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);

        do {
            $keys = $this->redis->scan($cursor, $this->prefix . ':*', 200);
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
        } while ($cursor !== 0);

        $this->redis->setOption(Redis::OPT_SCAN, $prev);
    }
}
