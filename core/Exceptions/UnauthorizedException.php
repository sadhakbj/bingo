<?php

declare(strict_types=1);

namespace Core\Exceptions;

class UnauthorizedException extends HttpException
{
    public function __construct(string $message = 'Unauthorized', ?\Throwable $previous = null)
    {
        parent::__construct(401, $message, $previous);
    }
}
