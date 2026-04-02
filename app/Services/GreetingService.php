<?php

declare(strict_types=1);

namespace App\Services;

use Config\AppConfig;

readonly class GreetingService
{
    public function __construct(public AppConfig $config) {}

    public function greet(string $name): string
    {
        return "Hello, {$name}! Welcome to {$this->config->name}.";
    }
}
