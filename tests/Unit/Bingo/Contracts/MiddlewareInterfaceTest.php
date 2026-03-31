<?php

declare(strict_types=1);

namespace Tests\Unit\Bingo\Contracts;

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\LogMiddleware;
use Bingo\Contracts\MiddlewareInterface;
use Bingo\Http\Middleware\BodyParserMiddleware;
use Bingo\Http\Middleware\CompressionMiddleware;
use Bingo\Http\Middleware\CorsMiddleware;
use Bingo\Http\Middleware\RateLimitMiddleware;
use Bingo\Http\Middleware\RequestIdMiddleware;
use Bingo\Http\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\TestCase;

class MiddlewareInterfaceTest extends TestCase
{
    /**
     * Every middleware in the framework must implement MiddlewareInterface.
     * This test acts as a contract enforcement check — if you add a middleware
     * and forget the interface, this fails.
     */
    public function test_all_middleware_implement_the_interface(): void
    {
        $middlewareClasses = [
            // Core HTTP middleware
            CorsMiddleware::class,
            BodyParserMiddleware::class,
            CompressionMiddleware::class,
            SecurityHeadersMiddleware::class,
            RequestIdMiddleware::class,
            RateLimitMiddleware::class,
            // App middleware
            AuthMiddleware::class,
            LogMiddleware::class,
        ];

        foreach ($middlewareClasses as $class) {
            $this->assertTrue(
                is_a($class, MiddlewareInterface::class, allow_string: true),
                "$class must implement MiddlewareInterface"
            );
        }
    }

    public function test_interface_handle_method_signature_is_enforced(): void
    {
        $reflection = new \ReflectionMethod(MiddlewareInterface::class, 'handle');
        $params = $reflection->getParameters();

        $this->assertCount(2, $params);
        $this->assertSame('request', $params[0]->getName());
        $this->assertSame('next', $params[1]->getName());
        $this->assertFalse($params[1]->allowsNull(), '$next must not be nullable');
    }
}
