<?php

declare(strict_types=1);

namespace Tests\Unit\Bingo\RateLimit;

use Bingo\RateLimit\RateLimiter;
use Bingo\RateLimit\Store\FileStore;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bingo_rl_test_' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private function limiter(): RateLimiter
    {
        return new RateLimiter(new FileStore($this->dir));
    }

    public function test_first_attempt_is_allowed(): void
    {
        $result = $this->limiter()->attempt('ip:1.2.3.4', limit: 10, windowSeconds: 60);

        $this->assertTrue($result->isAllowed());
        $this->assertSame(10, $result->limit);
        $this->assertSame(0, $result->retryAfter);
    }

    public function test_remaining_decrements_on_each_attempt(): void
    {
        $limiter = $this->limiter();

        $r1 = $limiter->attempt('key', 5, 60);
        $r2 = $limiter->attempt('key', 5, 60);
        $r3 = $limiter->attempt('key', 5, 60);

        $this->assertTrue($r1->isAllowed());
        $this->assertTrue($r2->isAllowed());
        $this->assertTrue($r3->isAllowed());
        $this->assertGreaterThan($r2->remaining, $r1->remaining);
        $this->assertGreaterThan($r3->remaining, $r2->remaining);
    }

    public function test_request_denied_when_limit_exceeded(): void
    {
        $limiter = $this->limiter();
        $limit   = 3;

        for ($i = 0; $i < $limit; $i++) {
            $limiter->attempt('key', $limit, 60);
        }

        $result = $limiter->attempt('key', $limit, 60);

        $this->assertTrue($result->isDenied());
        $this->assertSame(0, $result->remaining);
        $this->assertGreaterThan(0, $result->retryAfter);
    }

    public function test_denied_result_has_correct_limit(): void
    {
        $limiter = $this->limiter();

        for ($i = 0; $i < 5; $i++) {
            $limiter->attempt('key', 5, 60);
        }

        $result = $limiter->attempt('key', 5, 60);
        $this->assertSame(5, $result->limit);
    }

    public function test_reset_at_is_in_the_future(): void
    {
        $result = $this->limiter()->attempt('key', 10, 60);
        $this->assertGreaterThan(time(), $result->resetAt);
    }

    public function test_different_keys_have_independent_counters(): void
    {
        $limiter = $this->limiter();
        $limit   = 2;

        $limiter->attempt('key_a', $limit, 60);
        $limiter->attempt('key_a', $limit, 60);

        $resultA = $limiter->attempt('key_a', $limit, 60);
        $resultB = $limiter->attempt('key_b', $limit, 60);

        $this->assertTrue($resultA->isDenied());
        $this->assertTrue($resultB->isAllowed());
    }

    public function test_clear_resets_counter_so_requests_are_allowed_again(): void
    {
        $limiter = $this->limiter();

        $limiter->attempt('key', 1, 60);
        $this->assertTrue($limiter->attempt('key', 1, 60)->isDenied());

        $limiter->clear('key');

        $this->assertTrue($limiter->attempt('key', 1, 60)->isAllowed());
    }

    public function test_too_many_attempts_returns_true_when_limit_reached(): void
    {
        $limiter = $this->limiter();

        $limiter->attempt('key', 3, 60);
        $limiter->attempt('key', 3, 60);
        $limiter->attempt('key', 3, 60);

        $this->assertTrue($limiter->tooManyAttempts('key', 3, 60));
    }

    public function test_too_many_attempts_returns_false_when_under_limit(): void
    {
        $limiter = $this->limiter();
        $limiter->attempt('key', 10, 60);

        $this->assertFalse($limiter->tooManyAttempts('key', 10, 60));
    }

    public function test_too_many_attempts_does_not_increment_counter(): void
    {
        $limiter = $this->limiter();
        $limiter->attempt('key', 5, 60);

        $limiter->tooManyAttempts('key', 5, 60);
        $limiter->tooManyAttempts('key', 5, 60);

        $result = $limiter->attempt('key', 5, 60);
        $this->assertTrue($result->isAllowed());
    }

    public function test_retry_after_is_positive_when_denied(): void
    {
        $limiter = $this->limiter();

        $limiter->attempt('key', 1, 60);
        $result = $limiter->attempt('key', 1, 60);

        $this->assertTrue($result->isDenied());
        $this->assertGreaterThanOrEqual(1, $result->retryAfter);
    }

    public function test_retry_after_is_zero_when_allowed(): void
    {
        $result = $this->limiter()->attempt('key', 10, 60);
        $this->assertSame(0, $result->retryAfter);
    }
}
