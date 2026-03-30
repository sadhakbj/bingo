<?php

declare(strict_types=1);

namespace App\Exceptions;

use Core\Exceptions\HttpException;

final class PaymentRequiredException extends HttpException
{
    public function __construct(string $message = 'Payment Required', ?\Throwable $previous = null)
    {
        parent::__construct(402, $message, $previous);
    }
}