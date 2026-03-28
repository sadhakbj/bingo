<?php

declare(strict_types=1);

namespace Core\Attributes\Route;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Query
{
    public function __construct(
        public readonly ?string $key = null
    ) {}
}
