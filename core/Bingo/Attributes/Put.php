<?php

declare(strict_types=1);

namespace Bingo\Attributes;

use Attribute;
use Bingo\Attributes\Route\Route;

#[Attribute(Attribute::TARGET_METHOD)]
class Put extends Route
{
    public function __construct(string $path)
    {
        parent::__construct($path, 'PUT');
    }
}
