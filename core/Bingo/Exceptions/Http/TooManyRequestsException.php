<?php

declare(strict_types=1);

namespace Bingo\Exceptions\Http;

use Bingo\Http\Response;
use Bingo\RateLimit\RateLimitResult;

class TooManyRequestsException extends HttpException
{
    public function __construct(
        string                           $message = 'Too Many Requests',
        public readonly ?RateLimitResult $result = null,
        ?\Throwable                      $previous = null,
        ?string                          $description = null,
    ) {
        parent::__construct(Response::HTTP_TOO_MANY_REQUESTS, $message, $previous, $description);
    }
}
