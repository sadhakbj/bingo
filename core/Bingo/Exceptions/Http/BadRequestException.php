<?php

declare(strict_types = 1);

namespace Bingo\Exceptions\Http;

use Bingo\Http\Response;

class BadRequestException extends HttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null, ?string $description = null)
    {
        parent::__construct(Response::HTTP_BAD_REQUEST, $message, $previous, $description);
    }
}
