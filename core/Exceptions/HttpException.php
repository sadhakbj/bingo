<?php

declare(strict_types=1);

namespace Core\Exceptions;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?: $this->defaultMessage(), 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** Short reason phrase for JSON `error` (NestJS-style) and default messages. */
    public static function phraseForStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'HTTP Error',
        };
    }

    protected function defaultMessage(): string
    {
        return self::phraseForStatusCode($this->statusCode);
    }
}
