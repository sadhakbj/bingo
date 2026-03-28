<?php

declare(strict_types=1);

namespace Tests\Stubs\Middleware;

use Core\Contracts\MiddlewareInterface;
use Core\DTOs\Http\ApiResponse;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Short-circuits the pipeline and returns 403.
 * Used to verify method-level middleware can block a request.
 */
class BlockingMiddlewareStub implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        return Response::json(ApiResponse::forbidden()->toArray(), 403);
    }
}
