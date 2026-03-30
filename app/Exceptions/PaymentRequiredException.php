<?php

declare(strict_types=1);

namespace App\Exceptions;

use Core\Exceptions\HttpException;
use Core\Http\Response;

final class PaymentRequiredException extends HttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null, ?string $description = null)
    {
        parent::__construct(Response::HTTP_PAYMENT_REQUIRED, $message, $previous, $description);
    }
}