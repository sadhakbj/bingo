<?php

declare(strict_types=1);

namespace Bingo\Exceptions;

use Bingo\Contracts\ExceptionHandlerInterface;
use Bingo\Exceptions\Http\HttpException;
use Bingo\Exceptions\Http\TooManyRequestsException;
use Bingo\Http\Response;
use Bingo\Validation\ValidationException;
use Psr\Log\LoggerInterface;

/**
 * Default JSON errors: statusCode, message, error (+ optional details in debug).
 */
class ExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly bool $debug = false,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function handle(\Throwable $e): Response
    {
        $this->log($e);

        return match (true) {
            $e instanceof ValidationException => $this->handleValidation($e),
            $e instanceof HttpException       => $this->handleHttp($e),
            default                           => $this->handleGeneric($e),
        };
    }

    private function log(\Throwable $e): void
    {
        if ($this->logger === null) {
            return;
        }

        $statusCode = $e instanceof HttpException ? $e->getStatusCode() : 500;
        $context    = [
            'exception' => $e::class,
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
        ];

        if ($statusCode >= 500) {
            $this->logger->error($e->getMessage(), $context);
        } else {
            $this->logger->info($e->getMessage(), $context);
        }
    }

    private function handleValidation(ValidationException $e): Response
    {
        return $this->errorEnvelope(
            422,
            $e->errors,
            HttpException::phraseForStatusCode(422),
        );
    }

    private function handleHttp(HttpException $e): Response
    {
        $status = $e->getStatusCode();
        $error  = $e->getDescription() ?? HttpException::phraseForStatusCode($status);
        $response = $this->errorEnvelope(
            $status,
            $e->getMessage(),
            $error,
        );

        if ($e instanceof TooManyRequestsException) {
            $this->applyRateLimitHeaders($response, $e);
        }

        return $response;
    }

    private function handleGeneric(\Throwable $e): Response
    {
        if ($this->debug) {
            return $this->errorEnvelope(
                500,
                $e->getMessage(),
                'Internal Server Error',
                [
                    'exception' => $e::class,
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                    'trace'     => explode("\n", $e->getTraceAsString()),
                ],
            );
        }

        return $this->errorEnvelope(
            500,
            'Internal Server Error',
            'Internal Server Error',
        );
    }

    /**
     * @param string|array<string, string> $message String or field map (validation)
     * @param array<string, mixed>|null    $details Only when debug / extended payloads
     */
    private function errorEnvelope(int $statusCode, string|array $message, string $error, ?array $details = null): Response
    {
        $body = [
            'statusCode' => $statusCode,
            'message'    => $message,
            'error'      => $error,
        ];

        if ($details !== null) {
            $body['details'] = $details;
        }

        return Response::json($body, $statusCode);
    }

    private function applyRateLimitHeaders(Response $response, TooManyRequestsException $e): void
    {
        $result = $e->result;
        if ($result === null) {
            return;
        }

        $response->headers->set('X-RateLimit-Limit',     (string) $result->limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $result->remaining);
        $response->headers->set('X-RateLimit-Reset',     (string) $result->resetAt);
        $response->headers->set('Retry-After',           (string) $result->retryAfter);
    }
}
