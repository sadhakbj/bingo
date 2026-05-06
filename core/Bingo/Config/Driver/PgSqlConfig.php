<?php

declare(strict_types=1);

namespace Bingo\Config\Driver;

use Bingo\Attributes\Config\Env;
use SensitiveParameter;

/**
 * PostgreSQL connection configuration.
 *
 * Read replica support — just set DB_READ_HOST in your .env:
 *
 *   DB_READ_HOST=192.168.1.2
 *   DB_STICKY=true
 *
 * Eloquent will route SELECT queries to the read host automatically.
 * Note: DB_PORT defaults to 5432. Override explicitly if sharing a .env
 * with a MySQL project that sets DB_PORT=3306.
 */
class PgSqlConfig implements DatabaseDriver
{
    public function __construct(
        #[Env('DB_HOST', default: '127.0.0.1')] public string $host = '127.0.0.1',

        #[Env('DB_PORT', default: 5432)] public int $port = 5432,

        #[Env('DB_DATABASE', default: '')] public string $database = '',

        #[Env('DB_USERNAME', default: '')]
        #[SensitiveParameter] public string $username = '',

        #[Env('DB_PASSWORD', default: '')]
        #[SensitiveParameter] public string $password = '',

        #[Env('DB_CHARSET', default: 'utf8')] public string $charset = 'utf8',

        #[Env('DB_SCHEMA', default: 'public')] public string $schema = 'public',

        public string $prefix = '',
        public bool $strict = true,

        // Read replica — optional. When set, Eloquent splits reads/writes.
        #[Env('DB_READ_HOST', default: null)] public ?string $readHost = null,

        #[Env('DB_STICKY', default: false)] public bool $sticky = false,
    ) {}

    public function toArray(): array
    {
        $config = [
            'driver'         => 'pgsql',
            'port'           => $this->port,
            'database'       => $this->database,
            'username'       => $this->username,
            'password'       => $this->password,
            'charset'        => $this->charset,
            'prefix'         => $this->prefix,
            'prefix_indexes' => true,
            'search_path'    => $this->schema,
            'strict'         => $this->strict,
        ];

        if ($this->readHost !== null) {
            $config['read']   = ['host' => [$this->readHost]];
            $config['write']  = ['host' => [$this->host]];
            $config['sticky'] = $this->sticky;
        } else {
            $config['host'] = $this->host;
        }

        return $config;
    }
}
