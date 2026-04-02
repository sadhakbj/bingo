# Configuration

Bingo uses typed configuration classes instead of configuration arrays.

Environment values are mapped into readonly or mutable PHP objects using `#[Env]` attributes.

## Application configuration

```php
final readonly class AppConfig
{
    public function __construct(
        #[Env('APP_NAME', default: 'Bingo')] public string $name,
        #[Env('APP_ENV', default: 'development')] public string $env,
        #[Env('APP_DEBUG', default: false)] public bool $debug,
        #[Env('APP_URL', default: 'http://localhost:8000')] public string $url,
    ) {}
}
```

These classes can be injected anywhere in the application.

## Database configuration

```php
final class DbConfig
{
    #[Env('DB_CONNECTION', default: 'sqlite')]
    public string $default = 'sqlite';

    public array $connections = [
        'sqlite' => SQLiteConfig::class,
        'mysql'  => MySqlConfig::class,
        'pgsql'  => PgSqlConfig::class,
    ];
}
```

Connection-specific classes can extend the framework base configuration and define their own `#[Env]` mappings.

## Environment variables

Common variables include:

```env
APP_NAME=Bingo
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret
DB_READ_HOST=
DB_STICKY=false
```

## Read replicas

Read replicas are supported through the database configuration layer. When `DB_READ_HOST` is set, Bingo enables read and write splitting automatically.

For multiple replicas, override `toArray()` in the driver config and return the read hosts as an array.
