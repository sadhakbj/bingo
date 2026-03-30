<?php

declare(strict_types=1);

namespace Bingo\Contracts;

use Bingo\Http\Response;

/**
 * Top-level boundary: convert any Throwable into an HTTP Response.
 * Register a custom implementation via Application::exceptionHandler() or
 * $app->singleton(ExceptionHandlerInterface::class, YourHandler::class).
 */
interface ExceptionHandlerInterface
{
    public function handle(\Throwable $e): Response;
}
