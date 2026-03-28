<?php

declare(strict_types=1);

namespace Core\Attributes\Route;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public readonly string $path,
        public string $method = 'GET' {
            set => strtoupper($value);
        },
    ) {}
}
