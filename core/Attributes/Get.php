<?php

declare(strict_types=1);

namespace Core\Attributes;

use Attribute;
use Core\Attributes\Route\Route;

#[Attribute(Attribute::TARGET_METHOD)]
class Get extends Route
{
    public function __construct(string $path)
    {
        parent::__construct($path, 'GET');
    }
}
