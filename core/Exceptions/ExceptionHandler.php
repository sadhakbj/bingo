<?php

declare(strict_types=1);

namespace Core\Exceptions;

use Core\Contracts\ExceptionHandlerInterface;
use Core\Http\Response;
use Core\Validation\ValidationException;

/**
 * Default NestJS-style JSON errors: statusCode, message, error (+ optional details in debug).
 */
class ExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(private readonly bool $debug = false) {}

    public function handle(\Throwable $e): Response
    {
        return match (true) {
            $e instanceof ValidationException => $this->handleValidation($e),
            $e instanceof HttpException       => $this->handleHttp($e),
            default                           => $this->handleGeneric($e),
        };
    }

    private function handleValidation(ValidationException $e): Response
    {
        return $this->nestResponse(
            422,
            $e->errors,
            'Unprocessable Entity',
        );
    }

    private function handleHttp(HttpException $e): Response
    {
        $status = $e->getStatusCode();
        $response = $this->nestResponse(
            $status,
            $e->getMessage(),
            HttpException::phraseForStatusCode($status),
        );

        if ($e instanceof TooManyRequestsException) {
            $this->applyRateLimitHeaders($response, $e);
        }

        return $response;
    }

    private function handleGeneric(\Throwable $e): Response
    {
        if ($this->debug) {
            return $this->nestResponse(
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

        return $this->nestResponse(
            500,
            'Internal Server Error',
            'Internal Server Error',
        );
    }

    /**
     * @param string|array<string, string> $message String or field map (validation)
     * @param array<string, mixed>|null    $details Only when debug / extended payloads
     */
    private function nestResponse(int $statusCode, string|array $message, string $error, ?array $details = null): Response
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
        if ($e->getRateLimitLimit() !== null) {
            $response->headers->set('X-RateLimit-Limit', (string) $e->getRateLimitLimit());
        }
        if ($e->getRateLimitRemaining() !== null) {
            $response->headers->set('X-RateLimit-Remaining', (string) $e->getRateLimitRemaining());
        }
        if ($e->getRateLimitReset() !== null) {
            $response->headers->set('X-RateLimit-Reset', (string) $e->getRateLimitReset());
        }
    }
}
