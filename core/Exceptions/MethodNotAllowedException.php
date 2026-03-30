<?php

declare(strict_types=1);

namespace Core\Exceptions;

final class MethodNotAllowedException extends HttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct(405, $message, $previous);
    }
}
