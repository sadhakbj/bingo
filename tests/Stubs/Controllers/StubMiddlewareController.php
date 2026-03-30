<?php

declare(strict_types=1);

namespace Tests\Stubs\Controllers;

use Bingo\Attributes\Middleware;
use Bingo\Attributes\Route\ApiController;
use Bingo\Attributes\Route\Get;
use Bingo\Http\Response;
use Tests\Stubs\Middleware\BlockingMiddlewareStub;
use Tests\Stubs\Middleware\TrackingMiddlewareStub;

/**
 * Class-level middleware: TrackingMiddlewareStub applies to ALL routes.
 * Method-level middleware: BlockingMiddlewareStub applies only to /mw-test/blocked.
 */
#[ApiController('/mw-test')]
#[Middleware([TrackingMiddlewareStub::class])]
class StubMiddlewareController
{
    #[Get('/tracked')]
    public function routeOne(): Response
    {
        return Response::json(['route' => 'one']);
    }

    #[Get('/open')]
    public function routeTwo(): Response
    {
        return Response::json(['route' => 'two']);
    }

    #[Get('/blocked')]
    #[Middleware([BlockingMiddlewareStub::class])]
    public function withMethodMiddleware(): Response
    {
        return Response::json(['should' => 'not reach here']);
    }
}
