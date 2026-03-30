<?php

declare(strict_types=1);

namespace Tests\Stubs\Middleware;

use Bingo\Contracts\HttpResponse;
use Bingo\Contracts\MiddlewareInterface;
use Bingo\DTOs\Http\ApiResponse;
use Bingo\Http\Request;
use Bingo\Http\Response;

/**
 * Short-circuits the pipeline and returns 403.
 * Used to verify method-level middleware can block a request.
 */
class BlockingMiddlewareStub implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): HttpResponse
    {
        return Response::json(ApiResponse::forbidden()->toArray(), 403);
    }
}
