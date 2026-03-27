<?php

namespace Core\Validation;

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