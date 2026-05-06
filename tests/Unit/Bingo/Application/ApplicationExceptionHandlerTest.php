<?php

declare(strict_types = 1);

namespace Tests\Unit\Bingo\Application;

use Bingo\Application;
use Bingo\Contracts\ExceptionHandlerInterface;
use Bingo\Http\Request;
use Bingo\Http\Response;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\Controllers\StubApiController;

class ApplicationExceptionHandlerTest extends TestCase
{
    private function makeApp(): Application
    {
        return Application::create(dirname(__DIR__, 4));
    }

    public function test_handle_uses_custom_exception_handler_instance(): void
    {
        $app = $this->makeApp();
        $app->controllers([StubApiController::class]);
        $app->exceptionHandler(new class implements ExceptionHandlerInterface {
            public function handle(\Throwable $e): Response
            {
                return Response::json(['handled' => 'custom'], 418);
            }
        });

        $response = $app->handle(Request::create('/stub/does-not-exist', 'GET'));

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame(['handled' => 'custom'], json_decode($response->getContent(), true));
    }

    public function test_explicit_exception_handler_overrides_container_binding(): void
    {
        $app = $this->makeApp();
        $app->singleton(ExceptionHandlerInterface::class, ContainerBoundHandler::class);
        $app->exceptionHandler(new InstanceHandler());

        $app->controllers([StubApiController::class]);
        $response = $app->handle(Request::create('/stub/missing', 'GET'));

        $this->assertSame(419, $response->getStatusCode());
    }

    public function test_container_bound_exception_handler_when_no_explicit_instance(): void
    {
        $app = $this->makeApp();
        $app->singleton(ExceptionHandlerInterface::class, ContainerBoundHandler::class);
        $app->controllers([StubApiController::class]);

        $response = $app->handle(Request::create('/stub/missing', 'GET'));

        $this->assertSame(420, $response->getStatusCode());
    }
}

final class InstanceHandler implements ExceptionHandlerInterface
{
    public function handle(\Throwable $e): Response
    {
        return Response::json([], 419);
    }
}

final class ContainerBoundHandler implements ExceptionHandlerInterface
{
    public function handle(\Throwable $e): Response
    {
        return Response::json([], 420);
    }
}
