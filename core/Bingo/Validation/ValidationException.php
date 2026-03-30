<?php

declare(strict_types=1);

namespace Bingo\Validation;

use Exception;

class ValidationException extends Exception
{
    public array $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;
        $message = 'Validation failed: ' . implode(', ', array_keys($errors));
        parent::__construct($message);
    }
}
