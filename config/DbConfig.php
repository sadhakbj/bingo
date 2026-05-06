<?php

declare(strict_types=1);

namespace Config;

use Bingo\Attributes\Config\Env;

/**
 * Database configuration.
 *
 * $default   — the active connection name, resolved from DB_CONNECTION.
 * $connections — map of connection name → driver config class.
 *
 * Declare only the connections your application actually uses.
 * Each value must be a class that implements DatabaseDriver.
 *
 * Example with multiple connections:
 *
 *   public array $connections = [
 *       'mysql' => MySqlConfig::class,
 *       'read'  => ReadMySqlConfig::class,   // read replica
 *       'pgsql' => PgSqlConfig::class,
 *   ];
 */
final class DbConfig
{
    #[Env('DB_CONNECTION', default: 'sqlite')]
    public string $default = 'sqlite';

    /**
     * @var array<string, class-string<\Bingo\Config\Driver\DatabaseDriver>>
     */
    public array $connections = [
        'sqlite' => SQLiteConfig::class,
        'mysql'  => MySqlConfig::class,
        // 'pgsql' => PgSqlConfig::class,
    ];
}
