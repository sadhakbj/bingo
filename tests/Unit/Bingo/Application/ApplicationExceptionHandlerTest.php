<?php

declare(strict_types=1);

namespace Tests\Unit\Bingo\Application;

use Bingo\Application;
use Bingo\Contracts\ExceptionHandlerInterface;
use Bingo\Contracts\HttpResponse;
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

    public function test_handle_normalizes_plain_symfony_response_into_framework_http_response(): void
    {
        $app = $this->makeApp();
        $app->controllers([StubApiController::class]);

        $response = $app->handle(Request::create('/stub/symfony-response', 'GET'));

        $this->assertInstanceOf(HttpResponse::class, $response);
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('raw symfony', $response->getContent());
        $this->assertSame('yes', $response->headers->get('X-Symfony'));
    }

    public function test_handle_converts_boot_failure_into_exception_handler_response(): void
    {
        $previousAppEnv  = getenv('APP_ENV');
        $_ENV['APP_ENV'] = 'production';
        putenv('APP_ENV=production');

        $basePath = sys_get_temp_dir() . '/bingo-boot-failure-' . bin2hex(random_bytes(8));
        mkdir($basePath, 0755, true);

        try {
            $app = Application::create($basePath);
            $app->exceptionHandler(new class implements ExceptionHandlerInterface {
                public function handle(\Throwable $e): Response
                {
                    return Response::json(['handled' => $e::class], 503);
                }
            });

            $response = $app->handle(Request::create('/boot-fail', 'GET'));

            $this->assertSame(503, $response->getStatusCode());
            $this->assertSame(\RuntimeException::class, json_decode($response->getContent(), true)['handled']);
        } finally {
            if ($previousAppEnv === false) {
                putenv('APP_ENV');
                unset($_ENV['APP_ENV']);
            } else {
                putenv('APP_ENV=' . $previousAppEnv);
                $_ENV['APP_ENV'] = $previousAppEnv;
            }

            $this->removeDirectory($basePath);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($path);
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
