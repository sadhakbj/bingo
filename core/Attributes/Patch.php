<?php

declare(strict_types=1);

namespace Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Patch extends Route
{
    public function __construct(string $path)
    {
        parent::__construct($path, 'PATCH');
    }
}
