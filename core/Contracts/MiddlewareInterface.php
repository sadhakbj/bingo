<?php

declare(strict_types=1);

namespace Core\Contracts;

use Core\Http\Request;
use Core\Http\Response;

interface MiddlewareInterface
{
    /**
     * Handle the incoming request.
     *
     * Must call $next($request) to pass to the next middleware or final handler.
     * May return early (short-circuit) without calling $next.
     */
    public function handle(Request $request, callable $next): Response;
}
