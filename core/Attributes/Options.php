<?php

namespace Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Options extends Route
{
    public function __construct(string $path)
    {
        parent::__construct($path, 'OPTIONS');
    }
}
