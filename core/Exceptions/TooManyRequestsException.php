<?php

declare(strict_types=1);

namespace Core\Exceptions;

/**
 * Optional rate-limit metadata for ExceptionHandler to set X-RateLimit-* headers.
 */
final class TooManyRequestsException extends HttpException
{
    public function __construct(
        string $message = 'Rate limit exceeded. Please try again later.',
        private readonly ?int $rateLimitLimit = null,
        private readonly ?int $rateLimitRemaining = null,
        private readonly ?int $rateLimitReset = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(429, $message, $previous);
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
