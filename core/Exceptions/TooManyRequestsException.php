<?php

declare(strict_types=1);

namespace Core\Exceptions;

use Core\Http\Response;

/**
 * Optional rate-limit metadata for ExceptionHandler to set X-RateLimit-* headers.
 */
class TooManyRequestsException extends HttpException
{
    public function __construct(
        string $message = 'Rate limit exceeded. Please try again later.',
        private readonly ?int $rateLimitLimit = null,
        private readonly ?int $rateLimitRemaining = null,
        private readonly ?int $rateLimitReset = null,
        ?\Throwable $previous = null,
        ?string $description = null,
    ) {
        parent::__construct(Response::HTTP_TOO_MANY_REQUESTS, $message, $previous, $description);
    }

    public function getRateLimitLimit(): ?int
    {
        return $this->rateLimitLimit;
    }

    public function getRateLimitRemaining(): ?int
    {
        return $this->rateLimitRemaining;
    }

    public function getRateLimitReset(): ?int
    {
        return $this->rateLimitReset;
    }
}
