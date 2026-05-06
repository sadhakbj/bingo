<?php

declare(strict_types=1);

namespace Bingo\Config\Driver;

use Bingo\Attributes\Config\Env;
use SensitiveParameter;

/**
 * MySQL / MariaDB connection configuration.
 *
 * Read replica support — just set DB_READ_HOST in your .env:
 *
 *   DB_READ_HOST=192.168.1.2
 *   DB_STICKY=true
 *
 * Eloquent will route SELECT queries to the read host and writes to the
 * primary host automatically. No extra config classes needed.
 *
 * For multiple read hosts, override toArray() in config/MySqlConfig.php:
 *
 *   public function toArray(): array {
 *       $config = parent::toArray();
 *       $config['read']['host'] = [env('DB_READ_1'), env('DB_READ_2')];
 *       return $config;
 *   }
 */
class MySqlConfig implements DatabaseDriver
{
    public function __construct(
        #[Env('DB_HOST', default: '127.0.0.1')] public string $host = '127.0.0.1',

        #[Env('DB_PORT', default: 3306)] public int $port = 3306,

        #[Env('DB_DATABASE', default: '')] public string $database = '',

        #[Env('DB_USERNAME', default: '')]
        #[SensitiveParameter] public string $username = '',

        #[Env('DB_PASSWORD', default: '')]
        #[SensitiveParameter] public string $password = '',

        #[Env('DB_CHARSET', default: 'utf8mb4')] public string $charset = 'utf8mb4',

        #[Env('DB_COLLATION', default: 'utf8mb4_unicode_ci')] public string $collation = 'utf8mb4_unicode_ci',

        public string $prefix = '',
        public bool $strict = true,

        // Read replica — optional. When set, Eloquent splits reads/writes.
        #[Env('DB_READ_HOST', default: null)] public ?string $readHost = null,

        #[Env('DB_STICKY', default: false)] public bool $sticky = false,
    ) {}

    public function toArray(): array
    {
        $config = [
            'driver'    => 'mysql',
            'port'      => $this->port,
            'database'  => $this->database,
            'username'  => $this->username,
            'password'  => $this->password,
            'charset'   => $this->charset,
            'collation' => $this->collation,
            'prefix'    => $this->prefix,
            'strict'    => $this->strict,
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
