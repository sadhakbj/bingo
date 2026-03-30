<?php

declare(strict_types=1);

namespace Bingo\Exceptions\Http;

use Bingo\Http\Response;

class NotImplementedException extends HttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null, ?string $description = null)
    {
        parent::__construct(Response::HTTP_NOT_IMPLEMENTED, $message, $previous, $description);
    }
}
