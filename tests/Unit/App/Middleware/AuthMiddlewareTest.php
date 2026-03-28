<?php

declare(strict_types=1);

namespace Tests\Unit\App\Middleware;

use App\Http\Middleware\AuthMiddleware;
use Core\Contracts\MiddlewareInterface;
use Core\Exceptions\UnauthorizedException;
use Core\Http\Request;
use Core\Http\Response;
use PHPUnit\Framework\TestCase;

class AuthMiddlewareTest extends TestCase
{
    private AuthMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new AuthMiddleware();
    }

    private function next(): callable
    {
        return fn(Request $req) => Response::json(['ok' => true]);
    }

    private function requestWithToken(string $token): Request
    {
        return Request::create('/test', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ]);
    }

    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    public function test_implements_middleware_interface(): void
    {
        $this->assertInstanceOf(MiddlewareInterface::class, $this->middleware);
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_valid_bearer_token_calls_next(): void
    {
        $called = false;
        $next = function (Request $req) use (&$called) {
            $called = true;
            return Response::json(['ok' => true]);
        };

        $request = $this->requestWithToken('valid-token-123');
        $this->middleware->handle($request, $next);

        $this->assertTrue($called);
    }

    public function test_token_is_attached_to_request_attributes(): void
    {
        $capturedRequest = null;
        $next = function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;
            return Response::json([]);
        };

        $this->middleware->handle($this->requestWithToken('my-token'), $next);

        $this->assertSame('my-token', $capturedRequest->attributes->get('bearer_token'));
    }

    public function test_valid_token_returns_next_response(): void
    {
        $response = $this->middleware->handle(
            $this->requestWithToken('abc'),
            fn($req) => Response::json(['data' => 'secret'], 200)
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Missing / invalid header
    // -------------------------------------------------------------------------

    public function test_missing_authorization_header_throws_unauthorized(): void
    {
        $this->expectException(UnauthorizedException::class);

        $request = Request::create('/test', 'GET');
        $this->middleware->handle($request, $this->next());
    }

    public function test_non_bearer_scheme_throws_unauthorized(): void
    {
        $this->expectException(UnauthorizedException::class);

        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Basic dXNlcjpwYXNz',
        ]);
        $this->middleware->handle($request, $this->next());
    }

    public function test_empty_bearer_token_throws_unauthorized(): void
    {
        $this->expectException(UnauthorizedException::class);

        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer    ',
        ]);
        $this->middleware->handle($request, $this->next());
    }

    public function test_unauthorized_exception_has_meaningful_message(): void
    {
        try {
            $this->middleware->handle(Request::create('/'), $this->next());
            $this->fail('Expected UnauthorizedException');
        } catch (UnauthorizedException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }
}
