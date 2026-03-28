<?php

declare(strict_types=1);

namespace Core\Exceptions;

class ConflictException extends HttpException
{
    public function __construct(string $message = 'Conflict', ?\Throwable $previous = null)
    {
        parent::__construct(409, $message, $previous);
    }
}
