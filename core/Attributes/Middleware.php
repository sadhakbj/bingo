<?php

namespace Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Middleware
{
    public array $middlewares;

    public function __construct(array $middlewares)
    {
        $this->middlewares = $middlewares;
    }
}
