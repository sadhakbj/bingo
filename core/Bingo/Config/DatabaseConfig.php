<?php

declare(strict_types=1);

namespace Bingo\Config;

use Bingo\Config\Driver\DatabaseDriver;

/**
 * Holds the resolved database connections for the application.
 * Built by the framework during boot — not meant to be instantiated by user code.
 */
final class DatabaseConfig
{
    /** @var array<string, DatabaseDriver> */
    private array $connections;

    public function __construct(
        private readonly string $defaultName,
        array                   $connections,
    ) {
        $this->connections = $connections;
    }

    public function defaultName(): string
    {
        return $this->defaultName;
    }

    /** @return array<string, DatabaseDriver> */
    public function all(): array
    {
        return $this->connections;
    }
}
