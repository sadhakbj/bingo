<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Router;

use Core\Http\Request;
use Core\Http\Response;
use Core\Router\Router;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\Controllers\StubApiController;
use Tests\Stubs\Controllers\StubPlainController;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    private function makeRequest(string $path, string $method = 'GET'): Request
    {
        return Request::create($path, $method);
    }

    // -------------------------------------------------------------------------
    // Route registration
    // -------------------------------------------------------------------------

    public function test_register_controller_discovers_routes_from_attributes(): void
    {
        $this->router->registerController(StubApiController::class);
        $routes = $this->router->getRoutes();

        $this->assertGreaterThanOrEqual(3, count($routes)); // hello, show, create, search
    }

    public function test_register_controller_applies_api_controller_prefix(): void
    {
        $this->router->registerController(StubApiController::class);
        $routes = $this->router->getRoutes();

        $paths = [];
        foreach ($routes as $route) {
            $paths[] = $route->getPath();
        }

        $this->assertContains('/stub/hello', $paths);
        $this->assertContains('/stub/users/{id}', $paths);
        $this->assertContains('/stub/create', $paths);
    }

    public function test_register_plain_controller_without_prefix(): void
    {
        $this->router->registerController(StubPlainController::class);
        $routes = $this->router->getRoutes();

        $paths = [];
        foreach ($routes as $route) {
            $paths[] = $route->getPath();
        }

        $this->assertContains('/plain', $paths);
    }

    // -------------------------------------------------------------------------
    // Route dispatch — ApiController
    // -------------------------------------------------------------------------

    public function test_dispatch_calls_correct_controller_method(): void
    {
        $this->router->registerController(StubApiController::class);
        $request = $this->makeRequest('/stub/hello', 'GET');

        $response = $this->router->dispatch($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_dispatch_injects_route_param(): void
    {
        $this->router->registerController(StubApiController::class);
        $request = $this->makeRequest('/stub/users/42', 'GET');

        $response = $this->router->dispatch($request);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(42, $body['id']);
    }

    public function test_dispatch_post_route_responds_correctly(): void
    {
        $this->router->registerController(StubApiController::class);
        $request = $this->makeRequest('/stub/create', 'POST');

        $response = $this->router->dispatch($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(201, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // 404 / 405
    // -------------------------------------------------------------------------

    public function test_dispatch_returns_404_for_unknown_route(): void
    {
        $this->router->registerController(StubApiController::class);
        $request = $this->makeRequest('/stub/nonexistent', 'GET');

        $response = $this->router->dispatch($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_dispatch_returns_405_for_wrong_method(): void
    {
        $this->router->registerController(StubApiController::class);
        $request = $this->makeRequest('/stub/hello', 'DELETE'); // only GET registered

        $response = $this->router->dispatch($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(405, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Trailing slash normalisation
    // -------------------------------------------------------------------------

    public function test_dispatch_normalizes_trailing_slash(): void
    {
        $this->router->registerController(StubApiController::class);
        $request = $this->makeRequest('/stub/hello/', 'GET'); // trailing slash

        $response = $this->router->dispatch($request);

        // Should match /stub/hello
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Middleware registry
    // -------------------------------------------------------------------------

    public function test_get_middlewares_for_unregistered_route_returns_empty_array(): void
    {
        $middlewares = $this->router->getMiddlewaresForRoute('nonexistent@action');

        $this->assertSame([], $middlewares);
    }

    // -------------------------------------------------------------------------
    // Plain (non-API) controller
    // -------------------------------------------------------------------------

    public function test_dispatch_plain_controller_returns_string(): void
    {
        $this->router->registerController(StubPlainController::class);
        $request = $this->makeRequest('/plain', 'GET');

        $result = $this->router->dispatch($request);

        $this->assertSame('plain response', $result);
    }
}
