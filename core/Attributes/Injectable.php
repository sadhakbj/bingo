<?php

declare(strict_types=1);

namespace Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Injectable
{
    public function __construct(
        public readonly string $scope = 'transient'
    ) {}
}
