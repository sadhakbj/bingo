<?php

declare(strict_types=1);

namespace Core\Exceptions;

use Core\Http\Response;
use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
        private readonly ?string $description = null,
    ) {
        $code = $this->statusCode;
        parent::__construct($message !== '' ? $message : self::phraseForStatusCode($code), 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Optional short label for JSON `error`. When set, the default exception handler
     * uses this instead of the standard phrase for the status code.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** Reason phrase from Symfony HttpFoundation (IANA-style); used for default `message` and JSON `error`. */
    public static function phraseForStatusCode(int $statusCode): string
    {
        return Response::$statusTexts[$statusCode] ?? 'HTTP Error';
    }
}
