<?php

declare(strict_types=1);

namespace Bingo\Http\Middleware;

use Bingo\Contracts\HttpResponse;
use Bingo\Contracts\MiddlewareInterface;
use Bingo\Exceptions\Http\TooManyRequestsException;
use Bingo\Http\Request;
use Bingo\Http\Response as BingoResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RateLimitMiddleware implements MiddlewareInterface
{
    private array $config;
    private static array $storage = []; // Simple in-memory storage for demo

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_requests' => 100, // Max requests per window
            'window_seconds' => 3600, // 1 hour window
            'key_generator' => null, // Custom key generator
            'skip_successful' => false, // Only count failed requests
            'headers' => true, // Send rate limit headers
        ], $config);
    }

    public function handle(Request $request, callable $next): HttpResponse
    {
        $key = $this->generateKey($request);
        $window = $this->getCurrentWindow();
        $fullKey = $key . ':' . $window;

        // Get current count
        $current = self::$storage[$fullKey] ?? 0;

        // Check if limit exceeded
        if ($current >= $this->config['max_requests']) {
            $resetTime = ($window + 1) * $this->config['window_seconds'];

            if (!$this->config['headers']) {
                throw new TooManyRequestsException();
            }

            throw new TooManyRequestsException(
                'Rate limit exceeded. Please try again later.',
                $this->config['max_requests'],
                0,
                $resetTime,
            );
        }

        // Process request
        $response = $next ? $next($request) : BingoResponse::json(['message' => 'OK']);

        // Increment counter (only count this request if configured)
        if (!$this->config['skip_successful'] || $response->getStatusCode() >= 400) {
            self::$storage[$fullKey] = $current + 1;
        }

        // Add rate limit headers
        if ($this->config['headers']) {
            $this->addRateLimitHeaders($response, self::$storage[$fullKey] ?? $current, $window);
        }

        return $response;
    }

    private function generateKey(Request $request): string
    {
        if ($this->config['key_generator'] && is_callable($this->config['key_generator'])) {
            return call_user_func($this->config['key_generator'], $request);
        }

        // Default to IP address
        return $request->getClientIp() ?: 'unknown';
    }

    private function getCurrentWindow(): int
    {
        return floor(time() / $this->config['window_seconds']);
    }

    private function addRateLimitHeaders(SymfonyResponse $response, int $current, int $window): void
    {
        $remaining = max(0, $this->config['max_requests'] - $current);
        $resetTime = ($window + 1) * $this->config['window_seconds'];

        $response->headers->set('X-RateLimit-Limit', (string) $this->config['max_requests']);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        $response->headers->set('X-RateLimit-Reset', (string) $resetTime);
    }

    public static function create(array $config = []): self
    {
        return new self($config);
    }

    public static function perMinute(int $maxRequests): self
    {
        return new self([
            'max_requests' => $maxRequests,
            'window_seconds' => 60
        ]);
    }

    public static function perHour(int $maxRequests): self
    {
        return new self([
            'max_requests' => $maxRequests,
            'window_seconds' => 3600
        ]);
    }
}
