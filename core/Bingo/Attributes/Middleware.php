<?php

declare(strict_types = 1);

namespace Bingo\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Middleware
{
    public array $middlewares;

    public function __construct(array $middlewares)
    {
        $this->middlewares = $middlewares;
    }
}
