<?php

declare(strict_types=1);

namespace Tests\Unit\Bingo\RateLimit\Store;

use Bingo\RateLimit\Store\InMemoryStore;
use PHPUnit\Framework\TestCase;

class InMemoryStoreTest extends TestCase
{
    protected function setUp(): void
    {
        InMemoryStore::flush();
    }

    public function test_count_returns_zero_for_unknown_key(): void
    {
        $store = new InMemoryStore();
        $this->assertSame(0, $store->count('key', 1));
    }

    public function test_increment_returns_new_count(): void
    {
        $store = new InMemoryStore();
        $this->assertSame(1, $store->increment('key', 1, 60));
        $this->assertSame(2, $store->increment('key', 1, 60));
        $this->assertSame(3, $store->increment('key', 1, 60));
    }

    public function test_count_reflects_increments(): void
    {
        $store = new InMemoryStore();
        $store->increment('key', 1, 60);
        $store->increment('key', 1, 60);

        $this->assertSame(2, $store->count('key', 1));
    }

    public function test_different_windows_are_independent(): void
    {
        $store = new InMemoryStore();
        $store->increment('key', 1, 60);
        $store->increment('key', 1, 60);
        $store->increment('key', 2, 60);

        $this->assertSame(2, $store->count('key', 1));
        $this->assertSame(1, $store->count('key', 2));
    }

    public function test_different_keys_are_independent(): void
    {
        $store = new InMemoryStore();
        $store->increment('key_a', 1, 60);
        $store->increment('key_b', 1, 60);
        $store->increment('key_b', 1, 60);

        $this->assertSame(1, $store->count('key_a', 1));
        $this->assertSame(2, $store->count('key_b', 1));
    }

    public function test_reset_clears_all_windows_for_key(): void
    {
        $store = new InMemoryStore();
        $store->increment('key', 1, 60);
        $store->increment('key', 2, 60);

        $store->reset('key');

        $this->assertSame(0, $store->count('key', 1));
        $this->assertSame(0, $store->count('key', 2));
    }

    public function test_reset_does_not_affect_other_keys(): void
    {
        $store = new InMemoryStore();
        $store->increment('key_a', 1, 60);
        $store->increment('key_b', 1, 60);

        $store->reset('key_a');

        $this->assertSame(0, $store->count('key_a', 1));
        $this->assertSame(1, $store->count('key_b', 1));
    }

    public function test_static_storage_is_shared_across_instances(): void
    {
        $store1 = new InMemoryStore();
        $store2 = new InMemoryStore();

        $store1->increment('key', 1, 60);

        $this->assertSame(1, $store2->count('key', 1));
    }

    public function test_flush_clears_all_state(): void
    {
        $store = new InMemoryStore();
        $store->increment('key_a', 1, 60);
        $store->increment('key_b', 2, 60);

        InMemoryStore::flush();

        $this->assertSame(0, $store->count('key_a', 1));
        $this->assertSame(0, $store->count('key_b', 2));
    }
}
