<?php

declare(strict_types = 1);

namespace Bingo\Exceptions\Http;

use Bingo\Http\Response;

class UnauthorizedException extends HttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null, ?string $description = null)
    {
        parent::__construct(Response::HTTP_UNAUTHORIZED, $message, $previous, $description);
    }
}
