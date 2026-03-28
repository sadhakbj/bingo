<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Core\Contracts\MiddlewareInterface;
use Core\Http\Request;
use Core\Http\Response;

class LogMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $startTime = hrtime(true);

        $response = $next($request);

        $durationMs = round((hrtime(true) - $startTime) / 1_000_000, 2);

        error_log(sprintf(
            '[%s] %s %s → %d (%s ms) | ID: %s',
            date('Y-m-d H:i:s'),
            $request->getMethod(),
            $request->getPathInfo(),
            $response->getStatusCode(),
            $durationMs,
            $request->attributes->get('request_id', '-'),
        ));

        return $response;
    }
}
