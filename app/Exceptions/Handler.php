<?php

declare(strict_types = 1);

namespace App\Exceptions;

use Bingo\Contracts\ExceptionHandlerInterface;
use Bingo\Exceptions\ExceptionHandler as CoreExceptionHandler;
use Bingo\Exceptions\Http\NotFoundException;
use Bingo\Http\Response;

/**
 * Application-owned exception → HTTP response mapping.
 *
 * When `core/` is consumed as a separate Composer package, you customize errors here
 * (or replace this class entirely). Wire it from bootstrap/app.php, for example:
 *
 *   $app->exceptionHandler(new Handler($app->debug));
 *
 * Or register via the container (see README) so you can inject services into this class.
 */
final class Handler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly bool $debug = false,
    ) {
    }

    public function handle(\Throwable $e): Response
    {
        /*
         * Examples — pick one style or combine (e.g. branch on $e, then fall back to core):
         *
         * 1) Plain JSON (any shape you want):
         *
         *    return Response::json([
         *        'ok'    => false,
         *        'error' => $e->getMessage(),
         *    ], 500);
         *
         * 2) RFC 7807 Problem+JSON for one case, default for the rest:
         *
         *    if ($e instanceof \Bingo\Exceptions\Http\HttpException) {
         *        return Response::json([
         *            'type'   => 'about:blank',
         *            'title'  => $e->getMessage(),
         *            'status' => $e->getStatusCode(),
         *        ], $e->getStatusCode(), ['Content-Type' => 'application/problem+json']);
         *    }
         *
         * 3) Reuse the package mapper only when you do not handle the exception yourself:
         *
         *    return (new CoreExceptionHandler($this->debug))->handle($e);
         */
        return new CoreExceptionHandler($this->debug)->handle($e);
    }
}
