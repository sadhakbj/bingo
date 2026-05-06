<?php

declare(strict_types = 1);

namespace Bingo\Attributes\Route;

use Attribute;

/**
 * Outgoing response header on a route (method or class).
 *
 * Repeat on the same method for multiple headers. Class-level headers apply first;
 * method-level entries override the same header name.
 *
 * If the response already has that header from your action, the attribute is skipped.
 *
 * @see Headers For reading **request** headers into an action parameter.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Header
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {
    }
}
