<?php

declare(strict_types=1);

namespace Tests\Unit\Bingo\Router;

use Bingo\Http\Request;
use Bingo\Http\Response;
use Bingo\Http\Router\Router;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\Controllers\StubMiddlewareController;

class RouterMiddlewareTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->registerController(StubMiddlewareController::class);
    }

    private function makeRequest(string $path, string $method = 'GET'): Request
    {
        return Request::create($path, $method);
    }

    public function test_method_level_middleware_is_registered(): void
    {
        $middlewares = $this->router->getMiddlewaresForRoute(
            StubMiddlewareController::class . '@withMethodMiddleware',
        );

        $this->assertNotEmpty($middlewares);
    }

    public function test_class_level_middleware_applies_to_all_routes(): void
    {
        $routeA = $this->router->getMiddlewaresForRoute(
            StubMiddlewareController::class . '@routeOne',
        );
        $routeB = $this->router->getMiddlewaresForRoute(
            StubMiddlewareController::class . '@routeTwo',
        );

        // Both routes should carry the class-level middleware
        $this->assertContains(\Tests\Stubs\Middleware\TrackingMiddlewareStub::class, $routeA);
        $this->assertContains(\Tests\Stubs\Middleware\TrackingMiddlewareStub::class, $routeB);
    }

    public function test_method_middleware_is_merged_after_class_middleware(): void
    {
        $middlewares = $this->router->getMiddlewaresForRoute(
            StubMiddlewareController::class . '@withMethodMiddleware',
        );

        // TrackingMiddlewareStub (class-level) should appear before BlockingMiddlewareStub (method-level)
        $classIdx  = array_search(\Tests\Stubs\Middleware\TrackingMiddlewareStub::class, $middlewares);
        $methodIdx = array_search(\Tests\Stubs\Middleware\BlockingMiddlewareStub::class, $middlewares);

        $this->assertLessThan($methodIdx, $classIdx, 'Class middleware must run before method middleware');
    }

    public function test_route_middleware_is_executed_on_dispatch(): void
    {
        $request  = $this->makeRequest('/mw-test/tracked', 'GET');
        $response = $this->router->dispatch($request);

        // TrackingMiddlewareStub sets X-Tracked header
        $this->assertSame('yes', $response->headers->get('X-Tracked'));
    }

    public function test_route_middleware_can_short_circuit(): void
    {
        $request  = $this->makeRequest('/mw-test/blocked', 'GET');
        $response = $this->router->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_route_without_middleware_dispatches_normally(): void
    {
        $request  = $this->makeRequest('/mw-test/open', 'GET');
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_route_middleware_normalizes_plain_symfony_response(): void
    {
        $request  = $this->makeRequest('/mw-test/symfony-tracked', 'GET');
        $response = $this->router->dispatch($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('middleware symfony', $response->getContent());
        $this->assertSame('yes', $response->headers->get('X-Tracked'));
        $this->assertSame('tracked', $response->headers->get('X-Symfony'));
    }
}
