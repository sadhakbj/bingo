<?php

declare(strict_types=1);

namespace Bingo\Http\Middleware;

use Bingo\Contracts\MiddlewareInterface;
use Bingo\Exceptions\Http\TooManyRequestsException;
use Bingo\Http\Request;
use Bingo\Http\Response;
use Bingo\RateLimit\RateLimiter;
use Bingo\RateLimit\RateLimitResult;
use Bingo\RateLimit\Store\FileStore;

class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var callable(Request): string */
    private $keyResolver;

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int         $limit         = 1_000,
        private readonly int         $windowSeconds = 60,
        ?callable                    $keyResolver   = null,
    ) {
        $this->keyResolver = $keyResolver ?? static fn(Request $r): string
            => 'rl:' . ($r->getClientIp() ?? 'unknown');
    }

    public function handle(Request $request, callable $next): Response
    {
        $key    = ($this->keyResolver)($request);
        $result = $this->limiter->attempt($key, $this->limit, $this->windowSeconds);

        if ($result->isDenied()) {
            throw new TooManyRequestsException(result: $result);
        }

        /** @var Response $response */
        $response = $next($request);

        $this->addHeaders($response, $result);

        return $response;
    }

    // -------------------------------------------------------------------------
    // Factory methods
    // -------------------------------------------------------------------------

    /**
     * 60 requests per minute, in-memory store.
     * Useful as a quick per-route limiter when you have no shared backend.
     */
    public static function perMinute(int $limit, ?RateLimiter $limiter = null): self
    {
        return new self($limiter ?? self::defaultLimiter(), $limit, 60);
    }

    /**
     * N requests per hour, in-memory store.
     */
    public static function perHour(int $limit, ?RateLimiter $limiter = null): self
    {
        return new self($limiter ?? self::defaultLimiter(), $limit, 3600);
    }

    /**
     * Fully configured instance — intended for the global production pipeline
     * when no DI container is available yet (static factory context).
     */
    public static function create(
        ?RateLimiter $limiter       = null,
        int          $limit         = 1_000,
        int          $windowSeconds = 60,
        ?callable    $keyResolver   = null,
    ): self {
        return new self($limiter ?? self::defaultLimiter(), $limit, $windowSeconds, $keyResolver);
    }

    /**
     * Build a middleware instance from a #[Throttle] attribute value.
     * The key resolver is scoped to the route name so counters are per-route per-IP.
     */
    public static function fromThrottle(
        RateLimiter $limiter,
        int         $requests,
        int         $per,
        string      $routeName,
    ): self {
        $resolver = static fn(Request $r): string
            => 'throttle:' . $routeName . ':' . ($r->getClientIp() ?? 'unknown');

        return new self($limiter, $requests, $per, $resolver);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function addHeaders(Response $response, RateLimitResult $result): void
    {
        $response->headers->set('X-RateLimit-Limit',     (string) $result->limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $result->remaining);
        $response->headers->set('X-RateLimit-Reset',     (string) $result->resetAt);
    }

    private static function defaultLimiter(): RateLimiter
    {
        return new RateLimiter(new FileStore(sys_get_temp_dir() . '/bingo-rate-limit'));
    }
}
