<?php

declare(strict_types = 1);

namespace Bingo\Exceptions\Http;

use Bingo\Http\Response;
use Bingo\Validation\ValidationException;

/**
 * Manual 422 responses. DTO validation still uses {@see ValidationException}.
 */
class UnprocessableEntityException extends HttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null, ?string $description = null)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, $message, $previous, $description);
    }
}
