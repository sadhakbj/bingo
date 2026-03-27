<?php

namespace Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Head extends Route
{
    public function __construct(string $path)
    {
        parent::__construct($path, 'HEAD');
    }
}
