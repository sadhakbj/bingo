<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Http\Middleware;

use Core\Http\Middleware\MiddlewarePipeline;
use Core\Http\Request;
use Core\Http\Response;
use PHPUnit\Framework\TestCase;

class MiddlewarePipelineTest extends TestCase
{
    private function makeRequest(string $path = '/test', string $method = 'GET'): Request
    {
        return Request::create($path, $method);
    }

    // -------------------------------------------------------------------------
    // Empty pipeline
    // -------------------------------------------------------------------------

    public function test_empty_pipeline_calls_final_handler(): void
    {
        $pipeline = MiddlewarePipeline::create();
        $called = false;

        $pipeline->process($this->makeRequest(), function (Request $req) use (&$called) {
            $called = true;
            return Response::json(['ok' => true]);
        });

        $this->assertTrue($called);
    }

    public function test_empty_pipeline_without_handler_returns_ok_response(): void
    {
        $pipeline = MiddlewarePipeline::create();
        $response = $pipeline->process($this->makeRequest());

        $this->assertInstanceOf(Response::class, $response);
    }

    // -------------------------------------------------------------------------
    // Middleware execution order
    // -------------------------------------------------------------------------

    public function test_middleware_runs_before_final_handler(): void
    {
        $pipeline = MiddlewarePipeline::create();
        $order = [];

        $pipeline->addGlobal(function (Request $req, callable $next) use (&$order) {
            $order[] = 'middleware';
            return $next($req);
        });

        $pipeline->process($this->makeRequest(), function (Request $req) use (&$order) {
            $order[] = 'handler';
            return Response::json([]);
        });

        $this->assertSame(['middleware', 'handler'], $order);
    }

    public function test_multiple_middleware_run_in_registration_order(): void
    {
        $pipeline = MiddlewarePipeline::create();
        $order = [];

        $pipeline->addGlobal(function (Request $req, callable $next) use (&$order) {
            $order[] = 'first';
            return $next($req);
        });
        $pipeline->addGlobal(function (Request $req, callable $next) use (&$order) {
            $order[] = 'second';
            return $next($req);
        });
        $pipeline->addGlobal(function (Request $req, callable $next) use (&$order) {
            $order[] = 'third';
            return $next($req);
        });

        $pipeline->process($this->makeRequest(), function () use (&$order) {
            $order[] = 'handler';
            return Response::json([]);
        });

        $this->assertSame(['first', 'second', 'third', 'handler'], $order);
    }

    public function test_middleware_can_modify_response_after_next(): void
    {
        $pipeline = MiddlewarePipeline::create();

        $pipeline->addGlobal(function (Request $req, callable $next) {
            $response = $next($req);
            $response->headers->set('X-Custom', 'bingo');
            return $response;
        });

        $response = $pipeline->process($this->makeRequest(), function () {
            return Response::json(['ok' => true]);
        });

        $this->assertSame('bingo', $response->headers->get('X-Custom'));
    }

    // -------------------------------------------------------------------------
    // Short-circuit
    // -------------------------------------------------------------------------

    public function test_middleware_can_short_circuit_without_calling_next(): void
    {
        $pipeline = MiddlewarePipeline::create();
        $handlerCalled = false;

        $pipeline->addGlobal(function (Request $req, callable $next) {
            // Does NOT call $next — short-circuits
            return Response::json(['error' => 'blocked'], 403);
        });

        $response = $pipeline->process($this->makeRequest(), function () use (&$handlerCalled) {
            $handlerCalled = true;
            return Response::json([]);
        });

        $this->assertFalse($handlerCalled);
        $this->assertSame(403, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Object middleware with handle()
    // -------------------------------------------------------------------------

    public function test_object_middleware_with_handle_method_is_supported(): void
    {
        $pipeline = MiddlewarePipeline::create();
        $executed = false;

        $middleware = new class($executed) {
            public function __construct(private bool &$executed) {}

            public function handle(Request $request, callable $next): Response
            {
                $this->executed = true;
                return $next($request);
            }
        };

        $pipeline->addGlobal($middleware);
        $pipeline->process($this->makeRequest(), fn() => Response::json([]));

        $this->assertTrue($executed);
    }

    // -------------------------------------------------------------------------
    // count() / clear()
    // -------------------------------------------------------------------------

    public function test_count_reflects_total_middleware(): void
    {
        $pipeline = MiddlewarePipeline::create();
        $noop = fn($req, $next) => $next($req);

        $pipeline->addGlobal($noop);
        $pipeline->addGlobal($noop);

        $this->assertSame(2, $pipeline->count());
    }

    public function test_clear_removes_all_middleware(): void
    {
        $pipeline = MiddlewarePipeline::create();
        $noop = fn($req, $next) => $next($req);

        $pipeline->addGlobal($noop)->addGlobal($noop);
        $pipeline->clear();

        $this->assertSame(0, $pipeline->count());
    }

    public function test_use_method_is_alias_for_add_global(): void
    {
        $pipeline = MiddlewarePipeline::create();
        $noop = fn($req, $next) => $next($req);

        $pipeline->use($noop);

        $this->assertSame(1, $pipeline->count());
    }

    // -------------------------------------------------------------------------
    // Exception handling
    // -------------------------------------------------------------------------

    public function test_exception_in_middleware_propagates_to_caller(): void
    {
        // The pipeline does NOT swallow exceptions — it lets them bubble up so
        // that Application::handle()'s ExceptionHandler can process them properly
        // (correct status codes, debug traces, etc.). Catching here would produce
        // a generic "Middleware error" label regardless of what actually failed.
        $pipeline = MiddlewarePipeline::create();

        $pipeline->addGlobal(function (Request $req, callable $next) {
            throw new \RuntimeException('Something broke');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Something broke');

        $pipeline->process($this->makeRequest(), fn() => Response::json([]));
    }
}
