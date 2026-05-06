<?php

declare(strict_types = 1);

namespace Tests\Unit\Bingo\RateLimit\Store;

use Bingo\RateLimit\Store\FileStore;
use PHPUnit\Framework\TestCase;

class FileStoreTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bingo_rl_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    private function store(): FileStore
    {
        return new FileStore($this->dir);
    }

    public function test_count_returns_zero_for_unknown_key(): void
    {
        $this->assertSame(0, $this->store()->count('key', 1));
    }

    public function test_increment_creates_directory_if_missing(): void
    {
        $this->assertFalse(is_dir($this->dir));
        $this->store()->increment('key', 1, 60);
        $this->assertTrue(is_dir($this->dir));
    }

    public function test_increment_returns_new_count(): void
    {
        $store = $this->store();
        $this->assertSame(1, $store->increment('key', 1, 60));
        $this->assertSame(2, $store->increment('key', 1, 60));
        $this->assertSame(3, $store->increment('key', 1, 60));
    }

    public function test_count_reflects_increments(): void
    {
        $store = $this->store();
        $store->increment('key', 1, 60);
        $store->increment('key', 1, 60);

        $this->assertSame(2, $store->count('key', 1));
    }

    public function test_different_windows_are_independent(): void
    {
        $store = $this->store();
        $store->increment('key', 1, 60);
        $store->increment('key', 2, 60);

        $this->assertSame(1, $store->count('key', 1));
        $this->assertSame(1, $store->count('key', 2));
    }

    public function test_different_keys_are_independent(): void
    {
        $store = $this->store();
        $store->increment('key_a', 1, 60);
        $store->increment('key_a', 1, 60);
        $store->increment('key_b', 1, 60);

        $this->assertSame(2, $store->count('key_a', 1));
        $this->assertSame(1, $store->count('key_b', 1));
    }

    public function test_reset_removes_all_windows_for_key(): void
    {
        $store = $this->store();
        $store->increment('key', 1, 60);
        $store->increment('key', 2, 60);

        $store->reset('key');

        $this->assertSame(0, $store->count('key', 1));
        $this->assertSame(0, $store->count('key', 2));
    }

    public function test_reset_does_not_affect_other_keys(): void
    {
        $store = $this->store();
        $store->increment('key_a', 1, 60);
        $store->increment('key_b', 1, 60);

        $store->reset('key_a');

        $this->assertSame(0, $store->count('key_a', 1));
        $this->assertSame(1, $store->count('key_b', 1));
    }

    public function test_count_returns_zero_for_expired_entry(): void
    {
        $store = $this->store();
        // Increment with a tiny TTL
        $store->increment('key', 1, 60);

        // Manually expire the file by overwriting with past expires_at
        $path = new \ReflectionClass($store)
            ->getMethod('path')
            ->invoke($store, 'key', 1);

        file_put_contents($path, json_encode(['count' => 99, 'expires_at' => time() - 1]));

        // Should treat as 0
        $this->assertSame(0, $store->count('key', 1));
    }

    public function test_data_persists_across_instances(): void
    {
        new FileStore($this->dir)->increment('key', 1, 60);
        new FileStore($this->dir)->increment('key', 1, 60);

        $this->assertSame(2, new FileStore($this->dir)->count('key', 1));
    }
}
