<?php

declare(strict_types=1);

namespace Bingo\Attributes\Route;

use Attribute;

/**
 * Declarative HTTP status for a route (method or class).
 *
 * Applied after the action runs: only when the response status is still **200**, so
 * explicit `Response::json(..., 201)` or `setStatusCode()` in code still win.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class HttpCode
{
    public function __construct(
        public readonly int $code,
    ) {}
}
