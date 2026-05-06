<?php

declare(strict_types = 1);

namespace Tests\Stubs\Middleware;

use Bingo\Contracts\HttpResponse;
use Bingo\Contracts\MiddlewareInterface;
use Bingo\Http\Request;

/**
 * Passes through but sets X-Tracked header on the response.
 * Used to verify class-level middleware actually runs.
 */
class TrackingMiddlewareStub implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): HttpResponse
    {
        $response = $next($request);
        $response->headers->set('X-Tracked', 'yes');
        return $response;
    }
}
