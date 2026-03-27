<?php

namespace Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Post extends Route
{
    public function __construct(string $path)
    {
        parent::__construct($path, 'POST');
    }
}
