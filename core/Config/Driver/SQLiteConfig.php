<?php

declare(strict_types=1);

namespace Core\Config\Driver;

use Core\Attributes\Config\Env;

/**
 * SQLite connection configuration.
 *
 * DB_DATABASE may be a relative path ("database/database.sqlite"),
 * an absolute path, or empty — toArray() resolves it via base_path().
 */
class SQLiteConfig implements DatabaseDriver
{
    public function __construct(
        #[Env('DB_DATABASE', default: 'database/database.sqlite')]
        public string $database = 'database/database.sqlite',

        public string $prefix = '',
    ) {}

    public function toArray(): array
    {
        $db = $this->database ?: 'database/database.sqlite';

        if (!str_starts_with($db, '/')) {
            $db = base_path($db);
        }

        return [
            'driver'   => 'sqlite',
            'database' => $db,
            'prefix'   => $this->prefix,
        ];
    }
}
