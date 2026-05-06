<?php

declare(strict_types = 1);

namespace App\Http\Middleware;

use Bingo\Contracts\HttpResponse;
use Bingo\Contracts\MiddlewareInterface;
use Bingo\Exceptions\Http\UnauthorizedException;
use Bingo\Http\Request;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): HttpResponse
    {
        $authorization = $request->headers->get('Authorization');

        if (!$authorization || !str_starts_with($authorization, 'Bearer ')) {
            throw new UnauthorizedException('Missing or invalid Authorization header');
        }

        $token = substr($authorization, 7);

        if (empty(trim($token))) {
            throw new UnauthorizedException('Bearer token must not be empty');
        }

        // Token is present — attach it to request attributes for downstream use
        $request->attributes->set('bearer_token', $token);

        return $next($request);
    }
}
