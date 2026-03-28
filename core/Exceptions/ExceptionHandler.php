<?php

declare(strict_types=1);

namespace Core\Exceptions;

use Core\DTOs\Http\ApiResponse;
use Core\Http\Response;
use Core\Validation\ValidationException;

class ExceptionHandler
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
        $response = ApiResponse::validation($e->errors);

        return Response::json($response->toArray(), 422);
    }

    private function handleHttp(HttpException $e): Response
    {
        $response = ApiResponse::error($e->getMessage(), statusCode: $e->getStatusCode());

        return Response::json($response->toArray(), $e->getStatusCode());
    }

    private function handleGeneric(\Throwable $e): Response
    {
        $data = $this->debug ? [
            'exception' => $e::class,
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => explode("\n", $e->getTraceAsString()),
        ] : null;

        $message = $this->debug ? $e->getMessage() : 'Internal Server Error';
        $response = ApiResponse::error($message, null, 500, $data);

        return Response::json($response->toArray(), 500);
    }
}
