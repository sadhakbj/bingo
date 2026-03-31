<?php

declare(strict_types=1);

namespace Bingo\Attributes\Route;

use Attribute;

/**
 * Read a **request** header into an action parameter.
 *
 * @see Header Set an **outgoing** response header on the route.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Headers
{
    public function __construct(
        public readonly ?string $key = null
    ) {}
}
