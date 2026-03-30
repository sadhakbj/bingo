<?php

declare(strict_types=1);

namespace Bingo\Config\Driver;

/**
 * Contract for a typed database driver configuration.
 *
 * Each driver (SQLite, MySQL, PostgreSQL) implements this interface.
 * DatabaseConfig holds one or more named DatabaseDriver instances and
 * delegates connection resolution to them.
 *
 * toArray() returns the Eloquent Capsule-compatible connection array.
 */
interface DatabaseDriver
{
    public function toArray(): array;
}
