<?php

declare(strict_types = 1);

namespace Tests\Unit\Bingo\Http\Middleware;

use Bingo\Exceptions\Http\TooManyRequestsException;
use Bingo\Http\Middleware\RateLimitMiddleware;
use Bingo\Http\Request;
use Bingo\Http\Response;
use Bingo\RateLimit\RateLimiter;
use Bingo\RateLimit\Store\FileStore;
use PHPUnit\Framework\TestCase;

class RateLimitMiddlewareTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bingo_rl_mw_test_' . uniqid();
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

    private function request(string $ip = '1.2.3.4'): Request
    {
        $req = Request::create('/test', 'GET');
        $req->server->set('REMOTE_ADDR', $ip);
        return $req;
    }

    private function okNext(): callable
    {
        return fn(Request $req) => Response::json(['ok' => true]);
    }

    public function test_allowed_request_passes_through(): void
    {
        $middleware = new RateLimitMiddleware($this->limiter(), limit: 10, windowSeconds: 60);

        $response = $middleware->handle($this->request(), $this->okNext());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_rate_limit_headers_added_to_allowed_response(): void
    {
        $middleware = new RateLimitMiddleware($this->limiter(), limit: 10, windowSeconds: 60);
        $response   = $middleware->handle($this->request(), $this->okNext());

        $this->assertNotNull($response->headers->get('X-RateLimit-Limit'));
        $this->assertNotNull($response->headers->get('X-RateLimit-Remaining'));
        $this->assertNotNull($response->headers->get('X-RateLimit-Reset'));
    }

    public function test_limit_header_equals_configured_limit(): void
    {
        $middleware = new RateLimitMiddleware($this->limiter(), limit: 42, windowSeconds: 60);
        $response   = $middleware->handle($this->request(), $this->okNext());

        $this->assertSame('42', $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_remaining_decrements_across_requests(): void
    {
        $limiter    = $this->limiter();
        $middleware = new RateLimitMiddleware($limiter, limit: 5, windowSeconds: 60);
        $request    = $this->request();
        $next       = $this->okNext();

        $r1 = $middleware->handle($request, $next);
        $r2 = $middleware->handle($request, $next);

        $remaining1 = (int) $r1->headers->get('X-RateLimit-Remaining');
        $remaining2 = (int) $r2->headers->get('X-RateLimit-Remaining');

        $this->assertGreaterThan($remaining2, $remaining1);
    }

    public function test_throws_too_many_requests_when_limit_exceeded(): void
    {
        $limiter    = $this->limiter();
        $middleware = new RateLimitMiddleware($limiter, limit: 2, windowSeconds: 60);
        $request    = $this->request();
        $next       = $this->okNext();

        $middleware->handle($request, $next);
        $middleware->handle($request, $next);

        $this->expectException(TooManyRequestsException::class);
        $middleware->handle($request, $next);
    }

    public function test_too_many_requests_exception_carries_result(): void
    {
        $limiter    = $this->limiter();
        $middleware = new RateLimitMiddleware($limiter, limit: 1, windowSeconds: 60);
        $request    = $this->request();
        $next       = $this->okNext();

        $middleware->handle($request, $next);

        try {
            $middleware->handle($request, $next);
            $this->fail('Expected TooManyRequestsException');
        } catch (TooManyRequestsException $e) {
            $this->assertNotNull($e->result);
            $this->assertSame(1, $e->result->limit);
            $this->assertSame(0, $e->result->remaining);
            $this->assertGreaterThan(0, $e->result->retryAfter);
        }
    }

    public function test_different_ips_have_independent_limits(): void
    {
        $limiter    = $this->limiter();
        $middleware = new RateLimitMiddleware($limiter, limit: 1, windowSeconds: 60);
        $next       = $this->okNext();

        $middleware->handle($this->request('1.1.1.1'), $next);

        $this->expectException(TooManyRequestsException::class);
        $middleware->handle($this->request('1.1.1.1'), $next);
    }

    public function test_second_ip_not_affected_by_first_ip_limit(): void
    {
        $limiter    = $this->limiter();
        $middleware = new RateLimitMiddleware($limiter, limit: 1, windowSeconds: 60);
        $next       = $this->okNext();

        $middleware->handle($this->request('1.1.1.1'), $next);
        $response = $middleware->handle($this->request('2.2.2.2'), $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_custom_key_resolver_is_used(): void
    {
        $limiter    = $this->limiter();
        $middleware = new RateLimitMiddleware(
            $limiter,
            limit: 1,
            windowSeconds: 60,
            keyResolver: fn(Request $r) => 'fixed-key',
        );
        $next = $this->okNext();

        $middleware->handle($this->request('1.1.1.1'), $next);

        $this->expectException(TooManyRequestsException::class);
        $middleware->handle($this->request('9.9.9.9'), $next);
    }

    public function test_per_minute_factory(): void
    {
        $middleware = RateLimitMiddleware::perMinute(30);
        $this->assertInstanceOf(RateLimitMiddleware::class, $middleware);

        $response = $middleware->handle($this->request(), $this->okNext());
        $this->assertSame('30', $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_per_hour_factory(): void
    {
        $middleware = RateLimitMiddleware::perHour(500);
        $this->assertInstanceOf(RateLimitMiddleware::class, $middleware);

        $response = $middleware->handle($this->request(), $this->okNext());
        $this->assertSame('500', $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_from_throttle_scopes_key_to_route(): void
    {
        $limiter = $this->limiter();

        $mw1 = RateLimitMiddleware::fromThrottle($limiter, 1, 60, 'route_a');
        $mw2 = RateLimitMiddleware::fromThrottle($limiter, 1, 60, 'route_b');

        $request = $this->request();
        $next    = $this->okNext();

        $mw1->handle($request, $next);

        $response = $mw2->handle($request, $next);
        $this->assertSame(200, $response->getStatusCode());
    }
}
