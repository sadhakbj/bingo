<?php

declare(strict_types = 1);

namespace Bingo\Attributes\Provider;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ServiceProvider
{
    public function __construct()
    {
    }
}
