# Configuration

Bingo uses typed PHP objects for configuration instead of configuration arrays. Environment variables are mapped into constructor parameters or public properties using the `#[Env]` attribute.

All config classes live in `config/` and are automatically resolved and injected anywhere in the application.

In local development, these values usually come from `.env`. In Docker or Kubernetes,
they can come directly from the process environment, so a `.env` file is not required
in those deployments.

---

## Resolution Order

Configuration values are resolved in this order:

1. Environment variables already present in the process (`$_ENV` / `getenv()`).
2. Values loaded from `.env` when that file exists.
3. Defaults declared in `#[Env(..., default: ...)]`.

If no value is available from any of those sources and the target config property is
required, Bingo throws a configuration error during boot.

That means all of these are valid:

- local development with a `.env` file
- local development using shell environment variables only
- Docker or Kubernetes injecting environment variables directly

---

## How It Works

The `#[Env('VAR_NAME', default: value)]` attribute tells the framework which environment variable to read and what to use when it is absent.

```php
use Bingo\Attributes\Config\Env;

final readonly class AppConfig
{
    public function __construct(
        #[Env('APP_NAME', default: 'Bingo')]
        public string $name,

        #[Env('APP_ENV', default: 'development')]
        public string $env,

        #[Env('APP_DEBUG', default: false)]
        public bool $debug,

        #[Env('APP_URL', default: 'http://localhost:8000')]
        public string $url,
    ) {}
}
```

Inject `AppConfig` anywhere via the constructor:

```php
class SomeService
{
    public function __construct(private readonly AppConfig $config) {}
}
```

---

## Application Config (`AppConfig`)

| Environment variable | Type | Default | Description |
|---|---|---|---|
| `APP_NAME` | string | `Bingo` | Human-readable application name |
| `APP_ENV` | string | `development` | Environment name (`development`, `production`, etc.) |
| `APP_DEBUG` | bool | `false` | Enables verbose exception output |
| `APP_URL` | string | `http://localhost:8000` | Base URL used for link generation |

---

## Database Config (`DbConfig`)

```php
final class DbConfig
{
    #[Env('DB_CONNECTION', default: 'sqlite')]
    public string $default = 'sqlite';

    public array $connections = [
        'sqlite' => SQLiteConfig::class,
        'mysql'  => MySqlConfig::class,
        // 'pgsql' => PgSqlConfig::class,
    ];
}
```

### SQLite

| Variable | Default |
|---|---|
| `DB_DATABASE` | `database/database.sqlite` |

### MySQL

| Variable | Default | Description |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | Primary host |
| `DB_PORT` | `3306` | Port |
| `DB_DATABASE` | *(required)* | Database name |
| `DB_USERNAME` | `root` | Username |
| `DB_PASSWORD` | *(empty)* | Password |
| `DB_READ_HOST` | *(empty)* | Optional read-replica host |
| `DB_STICKY` | `false` | Keep writes on the write node for the rest of the request |

### PostgreSQL

| Variable | Default |
|---|---|
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `5432` |
| `DB_DATABASE` | *(required)* |
| `DB_USERNAME` | `postgres` |
| `DB_PASSWORD` | *(empty)* |

### Multiple Connections

Declare only the connections your application uses. Each value must be a class that implements `DatabaseDriver`:

```php
public array $connections = [
    'mysql' => MySqlConfig::class,
    'pgsql' => PgSqlConfig::class,
];
```

To add multiple read replicas, override `toArray()` in your driver config:

```php
// config/MySqlConfig.php
class MySqlConfig extends \Bingo\Config\Driver\MySqlConfig
{
    public function toArray(): array
    {
        $config = parent::toArray();
        $config['read']['host'] = [
            env('DB_READ_HOST_1', '192.168.1.1'),
            env('DB_READ_HOST_2', '192.168.1.2'),
        ];
        return $config;
    }
}
```

---

## CORS Config (`CorsConfig`)

| Variable | Default | Description |
|---|---|---|
| `CORS_ALLOWED_ORIGINS` | `*` | Comma-separated origins, or `*` for all |
| `CORS_ALLOWED_METHODS` | `GET,POST,PUT,PATCH,DELETE,OPTIONS` | Allowed HTTP methods |
| `CORS_ALLOWED_HEADERS` | `*` | Allowed request headers |
| `CORS_EXPOSED_HEADERS` | *(empty)* | Headers the browser may access |
| `CORS_MAX_AGE` | `86400` | Preflight cache duration in seconds |
| `CORS_SUPPORTS_CREDENTIALS` | `false` | Allow cookies / credentials |

Example `.env` for a single-page app:

```env
CORS_ALLOWED_ORIGINS=https://app.example.com,http://localhost:3000
CORS_SUPPORTS_CREDENTIALS=true
CORS_EXPOSED_HEADERS=X-Request-ID
```

---

## Logging Config (`LogConfig`)

| Variable | Default | Description |
|---|---|---|
| `LOG_CHANNEL` | `stack` | Channel name used in log records |
| `LOG_LEVEL` | `debug` | Minimum file log level |
| `LOG_STDERR_LEVEL` | `error` | Minimum stderr log level (Docker / k8s) |
| `LOG_PATH` | `storage/logs/bingo.log` | Rotating file destination |
| `LOG_FORMAT` | `text` | `text` (slog-style key=value) or `json` |
| `LOG_TIME_FORMAT` | `Y-m-d\TH:i:sP` | PHP date format for log timestamps |

---

## Rate Limit Config (`RateLimitConfig`)

| Variable | Default | Description |
|---|---|---|
| `RATE_LIMIT_ENABLED` | `true` | Enable or disable global rate limiting |
| `RATE_LIMIT_DRIVER` | `file` | `redis` (production) or `file` (dev fallback) |
| `RATE_LIMIT_REQUESTS` | `100` | Max requests per window |
| `RATE_LIMIT_WINDOW` | `60` | Sliding window length in seconds |
| `REDIS_HOST` | `127.0.0.1` | Redis server host |
| `REDIS_PORT` | `6379` | Redis server port |
| `REDIS_PASSWORD` | `null` | Redis password (leave as `null` for no auth) |
| `REDIS_DB` | `0` | Redis database index |

---

## Full `.env` Reference

```env
# Application
APP_NAME=Bingo
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Database (MySQL)
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=myapp
# DB_USERNAME=root
# DB_PASSWORD=secret
# DB_READ_HOST=
# DB_STICKY=false

# Database (PostgreSQL)
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=myapp
# DB_USERNAME=postgres
# DB_PASSWORD=secret

# CORS
CORS_ALLOWED_ORIGINS=*
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=*
CORS_EXPOSED_HEADERS=
CORS_MAX_AGE=86400
CORS_SUPPORTS_CREDENTIALS=false

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug
LOG_STDERR_LEVEL=error
LOG_PATH=storage/logs/bingo.log
LOG_FORMAT=text
LOG_TIME_FORMAT=Y-m-d\TH:i:sP

# Rate limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_DRIVER=file
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=60

# Redis (for rate limiting in production)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
```
