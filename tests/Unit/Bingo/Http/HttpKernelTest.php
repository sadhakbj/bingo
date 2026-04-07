<?php

declare(strict_types=1);

namespace Tests\Unit\Bingo\Http;

use Bingo\Contracts\ExceptionHandlerInterface;
use Bingo\Contracts\HttpResponse;
use Bingo\Http\HttpKernel;
use Bingo\Http\Middleware\MiddlewarePipeline;
use Bingo\Http\Request;
use Bingo\Http\Response;
use Bingo\Http\Router\Router;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Tests\Stubs\Controllers\StubApiController;

class HttpKernelTest extends TestCase
{
    public function test_handle_normalizes_plain_symfony_response(): void
    {
        $router = new Router();
        $router->registerController(StubApiController::class);

        $kernel = new HttpKernel(
            MiddlewarePipeline::create(),
            $router,
            static fn() => new class implements ExceptionHandlerInterface {
                public function handle(\Throwable $e): Response
                {
                    return Response::json(['handled' => false], 500);
                }
            },
        );

        $response = $kernel->handle(Request::create('/stub/symfony-response', 'GET'));

        $this->assertInstanceOf(HttpResponse::class, $response);
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('raw symfony', $response->getContent());
        $this->assertSame('yes', $response->headers->get('X-Symfony'));
    }

    public function test_handle_uses_exception_handler_resolver_when_dispatch_fails(): void
    {
        $router = new Router();
        $router->registerController(StubApiController::class);

        $kernel = new HttpKernel(
            MiddlewarePipeline::create(),
            $router,
            static fn() => new class implements ExceptionHandlerInterface {
                public function handle(\Throwable $e): Response
                {
                    return Response::json(['handled' => $e::class], 418);
                }
            },
        );

        $response = $kernel->handle(Request::create('/stub/does-not-exist', 'GET'));

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame(
            \Bingo\Exceptions\Http\NotFoundException::class,
            json_decode($response->getContent(), true)['handled'],
        );
    }
}
