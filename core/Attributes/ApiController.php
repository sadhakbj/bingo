<?php

declare(strict_types=1);

namespace Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ApiController
{
    public ?string $prefix;

    public function __construct(?string $prefix = null)
    {
        $this->prefix = $prefix;
    }
}
