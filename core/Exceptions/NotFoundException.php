<?php

declare(strict_types=1);

namespace Core\Exceptions;

use Core\Http\Response;

class NotFoundException extends HttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null, ?string $description = null)
    {
        parent::__construct(Response::HTTP_NOT_FOUND, $message, $previous, $description);
    }
}
