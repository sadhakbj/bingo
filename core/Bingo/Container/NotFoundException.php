<?php

declare(strict_types=1);

namespace Bingo\Container;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
    public function __construct(string $id, ?\Throwable $previous = null)
    {
        parent::__construct(
            "No binding found for '{$id}' and it cannot be auto-resolved.",
            0,
            $previous,
        );
    }
}
