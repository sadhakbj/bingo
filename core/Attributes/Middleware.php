<?php

declare(strict_types=1);

namespace Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD, Attribute::TARGET_CLASS)]
class Middleware
{
    public array $middlewares;

    public function __construct(array $middlewares)
    {
        $this->middlewares = $middlewares;
    }
}
