# Bingo

A PHP 8.5+ framework for API-first development. Attribute-driven routing, typed config, constructor injection, and Eloquent ORM — with zero boilerplate.

---

## Quick Start

```bash
git clone https://github.com/sadhakbj/bingo.git
cd bingo
composer install
cp .env.example .env
php -S localhost:8000 -t public
```

`public/index.php` is the entry point. `bootstrap/app.php` is where you wire everything:

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Application;

$app = Application::create();

$app->controllers([
    \App\Http\Controllers\UsersController::class,
]);

return $app;
```

`Application::create()` automatically loads `.env`, bootstraps typed config, boots Eloquent, and sets up the middleware pipeline.

---

## Configuration

Config classes live in `config/` and are wired to environment variables via `#[Env]` attributes. No arrays. No magic strings. Just typed properties.

### App Config

`config/AppConfig.php`:

```php
final readonly class AppConfig
{
    public function __construct(
        #[Env('APP_NAME',  default: 'Bingo')]            public string $name,
        #[Env('APP_ENV',   default: 'development')]      public string $env,
        #[Env('APP_DEBUG', default: false)]              public bool   $debug,
        #[Env('APP_URL',   default: 'http://localhost')] public string $url,
    ) {}
}
```

Inject it anywhere via the container:

```php
public function __construct(private readonly AppConfig $config) {}
```

### Database Config

`config/DbConfig.php` declares which connections your app uses:

```php
final class DbConfig
{
    #[Env('DB_CONNECTION', default: 'sqlite')]
    public string $default = 'sqlite';

    public array $connections = [
        'sqlite' => SQLiteConfig::class,
        // 'mysql' => MySqlConfig::class,
        // 'pgsql' => PgSqlConfig::class,
    ];
}
```

Each driver class (`config/MySqlConfig.php`, `config/SQLiteConfig.php`, `config/PgSqlConfig.php`) extends the framework base and inherits all `#[Env]` wiring. Customize by overriding there.

**Read replica** — set `DB_READ_HOST` in `.env`. Eloquent's `read`/`write` split is built in:

```env
DB_HOST=10.0.0.1
DB_READ_HOST=10.0.0.2
DB_STICKY=true
```

For multiple read hosts, override `toArray()` in `config/MySqlConfig.php`:

```php
public function toArray(): array {
    $config = parent::toArray();
    $config['read']['host'] = [
        env('DB_READ_HOST_1', '192.168.1.1'),
        env('DB_READ_HOST_2', '192.168.1.2'),
    ];
    return $config;
}
```

### Full `.env` reference

```env
APP_NAME=Bingo
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# MySQL / PostgreSQL
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret
DB_READ_HOST=      # optional read replica
DB_STICKY=false
```

---

## Routing

Define routes directly on controller methods with attributes. No route files.

```php
#[ApiController('/users')]
class UsersController
{
    #[Get('/')]
    public function index(): Response {}

    #[Get('/{id}')]
    public function show(#[Param('id')] int $id): Response {}

    #[Post('/')]
    public function create(#[Body] CreateUserDTO $dto): Response {}

    #[Put('/{id}')]
    public function update(#[Param('id')] int $id, #[Body] UpdateUserDTO $dto): Response {}

    #[Delete('/{id}')]
    public function destroy(#[Param('id')] int $id): Response {}
}
```

Register controllers in `bootstrap/app.php`:

```php
$app->controllers([
    UsersController::class,
    PostsController::class,
]);
```

> Specific routes (`/search`, `/upload`) must be declared **before** wildcard routes (`/{id}`) in the class to prevent the wildcard from matching first.

**Available route attributes:** `#[Get]` `#[Post]` `#[Put]` `#[Patch]` `#[Delete]` `#[Head]` `#[Options]`

---

## Parameter Binding

Extract request data using parameter attributes:

```php
public function handle(
    #[Body]                              CreateUserDTO $dto,      // JSON body → DTO
    #[Param('id')]                       int $id,                 // route segment
    #[Query('page')]                     int $page = 1,           // ?page=
    #[Headers('x-api-version')]          ?string $version = null, // request header
    #[Request]                           Request $request,        // full request object
    #[UploadedFile('avatar')]            ?UploadedFile $avatar = null, // single file
    #[UploadedFiles]                     array $files = [],       // all uploaded files
): Response
```

Query and param values are automatically cast to the declared PHP type (`int`, `bool`, `float`, `string`).

---

## Middleware

### Global middleware

```php
$app->use(\Core\Http\Middleware\CorsMiddleware::class)
    ->use(\Core\Http\Middleware\BodyParserMiddleware::class)
    ->use(\Core\Http\Middleware\SecurityHeadersMiddleware::class)
    ->use(\App\Http\Middleware\AuthMiddleware::class);
```

### Per-controller or per-route

```php
#[ApiController('/admin')]
#[Middleware([AuthMiddleware::class])]
class AdminController
{
    #[Get('/stats')]
    #[Middleware([LogMiddleware::class])]
    public function stats(): Response {}
}
```

### Built-in middleware

| Class | Purpose |
|-------|---------|
| `CorsMiddleware` | CORS headers and preflight handling |
| `BodyParserMiddleware` | Parses JSON and form-encoded request bodies |
| `SecurityHeadersMiddleware` | OWASP security headers (CSP, HSTS, etc.) |
| `CompressionMiddleware` | Gzip/deflate response compression |
| `RequestIdMiddleware` | Attaches a unique `X-Request-ID` to every request |
| `RateLimitMiddleware` | Per-IP request rate limiting |

### Custom middleware

```php
class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!$request->headers->get('Authorization')) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
```

The `$next($request)` call must be **outside** any try/catch that catches general exceptions — only catch errors that belong to the middleware itself.

---

## DTOs & Validation

DTOs extend `DataTransferObject` and declare properties with Symfony Validator constraints. The framework populates and validates them automatically when you use `#[Body]`.

```php
class CreateUserDTO extends DataTransferObject
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public readonly string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    public readonly string $name;

    #[Assert\Range(min: 18, max: 120)]
    public readonly ?int $age;
}
```

Validation failure returns `422` automatically:

```json
{
  "errors": {
    "email": "This value should not be blank.",
    "age": "This value should be between 18 and 120."
  }
}
```

---

## Dependency Injection

Constructor dependencies are resolved automatically. Register explicit bindings in `bootstrap/app.php` before `return $app`:

```php
// Interface → concrete
$app->singleton(\App\Contracts\MailerInterface::class, \App\Services\SmtpMailer::class);

// Transient (new instance per resolution)
$app->bind(\App\Services\ReportBuilder::class);

// Pre-built instance
$app->instance(\App\Config\PaymentConfig::class, new PaymentConfig(key: env('STRIPE_KEY')));
```

Concrete classes with typed constructors resolve automatically — no registration needed:

```php
class UsersController
{
    public function __construct(private readonly UserService $userService) {}
}
```

`AppConfig` and `DatabaseConfig` are pre-registered by the framework and injectable everywhere.

---

## Eloquent ORM

Standard Laravel Eloquent models work out of the box:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email'];
}
```

Migrations live in `database/migrations/` and run with the standard Eloquent migration tooling.

---

## Directory Structure

```
├── app/
│   ├── DTOs/               # Data transfer objects
│   ├── Http/
│   │   ├── Controllers/    # Route handlers
│   │   ├── Middleware/     # Custom middleware
│   │   └── Requests/       # (optional) typed request classes
│   ├── Models/             # Eloquent models
│   └── Services/           # Business logic
├── bootstrap/
│   └── app.php             # Application entry point
├── config/
│   ├── AppConfig.php       # Typed app config (#[Env])
│   ├── DbConfig.php        # Database connections
│   ├── MySqlConfig.php     # MySQL driver (customize here)
│   ├── PgSqlConfig.php     # PostgreSQL driver
│   └── SQLiteConfig.php    # SQLite driver
├── core/                   # Framework internals (don't modify)
├── database/
│   └── migrations/
├── public/
│   └── index.php           # Web entry point
└── .env
```

---

## Testing

```bash
composer test
```

Tests live in `tests/` mirroring the `app/` and `core/` structure.

---

## License

MIT
