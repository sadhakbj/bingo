<?php

declare(strict_types=1);

namespace Bingo\Attributes\Route;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Body
{
    public function __construct(
        public readonly ?string $dtoClass = null,
    ) {}
}
