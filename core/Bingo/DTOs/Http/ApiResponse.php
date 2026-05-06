<?php

declare(strict_types=1);

namespace Bingo\DTOs\Http;

use Bingo\Data\DataTransferObject;

class ApiResponse extends DataTransferObject
{
    public readonly bool $success;
    public readonly string $message;
    public readonly mixed $data;
    public readonly ?array $errors;
    public readonly ?array $meta;
    public readonly int $status_code;
    public readonly string $timestamp;

    public function __construct(array $data = [])
    {
        $data['timestamp'] = $data['timestamp'] ?? date('c');
        $data['success']   = $data['success'] ?? ($data['status_code'] ?? 200) < 400;
        parent::__construct($data);
    }

    public static function success(
        mixed  $data = null,
        string $message = 'Success',
        int    $statusCode = 200,
        ?array $meta = null,
    ): self {
        return new self([
            'success'     => true,
            'message'     => $message,
            'data'        => $data,
            'errors'      => null,
            'meta'        => $meta,
            'status_code' => $statusCode,
        ]);
    }

    public static function error(
        string $message = 'An error occurred',
        ?array $errors = null,
        int    $statusCode = 400,
        mixed  $data = null,
    ): self {
        return new self([
            'success'     => false,
            'message'     => $message,
            'data'        => $data,
            'errors'      => $errors,
            'meta'        => null,
            'status_code' => $statusCode,
        ]);
    }

    public static function validation(array $errors, string $message = 'Validation failed'): self
    {
        return self::error($message, $errors, 422);
    }

    public static function notFound(string $message = 'Resource not found'): self
    {
        return self::error($message, null, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return self::error($message, null, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): self
    {
        return self::error($message, null, 403);
    }
}
