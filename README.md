# Bingo

> A PHP 8.5+ framework for API-first development — attribute-driven routing, typed configuration, automatic dependency injection, and Laravel Eloquent ORM, with zero boilerplate.

Inspired by the structure of NestJS and the ergonomics of Laravel, but built from scratch on top of Symfony's HTTP, Routing, Validator, Console, and DI components.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Project Structure](#project-structure)
- [Configuration](#configuration)
  - [Application Config](#application-config)
  - [Database Config](#database-config)
  - [Environment Reference](#environment-reference)
- [Request Lifecycle](#request-lifecycle)
- [Routing](#routing)
- [Parameter Binding](#parameter-binding)
- [Middleware](#middleware)
  - [Global Middleware](#global-middleware)
  - [Controller and Route Middleware](#controller-and-route-middleware)
  - [Writing Custom Middleware](#writing-custom-middleware)
  - [Built-in Middleware](#built-in-middleware)
- [DTOs and Validation](#dtos-and-validation)
- [Dependency Injection](#dependency-injection)
- [Exception Handling](#exception-handling)
- [Eloquent ORM](#eloquent-orm)
- [CLI — bin/bingo](#cli--binbingo)
  - [Development Server](#development-server)
  - [Database Migrations](#database-migrations)
  - [Code Generators](#code-generators)
  - [Custom Commands](#custom-commands)
- [Testing](#testing)

---

## Requirements

- PHP **8.5** or higher
- Composer
- SQLite, MySQL 8+, or PostgreSQL 14+

---

## Installation

```bash
git clone https://github.com/sadhakbj/bingo.git
cd bingo
composer install
cp .env.example .env
```

Start the development server:

```bash
php bin/bingo serve
```

The application is now available at `http://127.0.0.1:8000`.

---

## Project Structure

```
├── app/
│   ├── Console/
│   │   └── Commands/         # Custom CLI commands
│   ├── Exceptions/
│   │   └── Handler.php       # Optional: your ExceptionHandlerInterface (error JSON shape)
│   ├── DTOs/                 # Data transfer objects (input + output)
│   ├── Http/
│   │   ├── Controllers/      # Route controllers
│   │   └── Middleware/       # Application middleware
│   ├── Models/               # Eloquent models
│   └── Services/             # Business logic
├── bin/
│   └── bingo                 # CLI entry point
├── bootstrap/
│   ├── app.php               # HTTP application bootstrap
│   └── console.php           # Console application bootstrap
├── config/
│   ├── AppConfig.php         # Typed application config
│   ├── DbConfig.php          # Database connection map
│   ├── MySqlConfig.php       # MySQL driver (customize here)
│   ├── PgSqlConfig.php       # PostgreSQL driver
│   └── SQLiteConfig.php      # SQLite driver
├── core/
│   ├── Bingo/                # Framework code (`Bingo\*` namespaces)
│   └── helpers.php           # `base_path()`, `env()`, …
├── database/
│   └── migrations/           # Migration files
├── public/
│   └── index.php             # Web entry point
├── tests/
└── .env
```

Everything under `app/` is yours. `core/` is the framework — you should not need to modify it.

---

## Configuration

Config classes live in `config/` and map environment variables to typed PHP properties through `#[Env]` attributes. There are no config arrays and no magic strings — only strongly-typed, injectable objects.

### Application Config

`config/AppConfig.php` is a `readonly` class populated automatically at boot:

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

Inject it anywhere via the DI container:

```php
class HealthController
{
    public function __construct(private readonly AppConfig $config) {}

    #[Get('/health')]
    public function index(): Response
    {
        return Response::json(['env' => $this->config->env]);
    }
}
```

### Database Config

`config/DbConfig.php` declares which connections your application uses and which one is active by default:

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

Each driver class (e.g. `config/MySqlConfig.php`) extends the corresponding framework base and inherits its `#[Env]` wiring. Override or extend them there — the framework never touches those files.

**Read replicas** are supported out of the box. Set `DB_READ_HOST` and Eloquent's `read`/`write` split activates automatically:

```env
DB_HOST=10.0.0.1
DB_READ_HOST=10.0.0.2
DB_STICKY=true
```

For multiple read replicas, override `toArray()` in your driver config:

```php
// config/MySqlConfig.php
public function toArray(): array
{
    $config = parent::toArray();
    $config['read']['host'] = [
        env('DB_READ_HOST_1', '10.0.0.2'),
        env('DB_READ_HOST_2', '10.0.0.3'),
    ];
    return $config;
}
```

### Environment Reference

```env
# Application
APP_NAME=Bingo
APP_ENV=development        # development | production
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Database (MySQL / PostgreSQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret
DB_READ_HOST=              # optional — activates read/write split
DB_STICKY=false

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080

# Rate limiting
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=3600
```

---

## Request Lifecycle

```
public/index.php
  → bootstrap/app.php          (register controllers and DI bindings)
  → Application::run()
  → container->compile()       (DI container freezes — singletons locked in)
  → Request::createFromGlobals()
  → MiddlewarePipeline::process()
      CorsMiddleware
      → BodyParserMiddleware
        → CompressionMiddleware
          → SecurityHeadersMiddleware
            → RequestIdMiddleware
              → RateLimitMiddleware (production only)
                → Router::dispatch()
                    PHP Reflection resolves controller + method params
                    (#[Body], #[Param], #[Query], #[Headers], ...)
                    → controller method called
                    ← Response
  ← Response::send()
```

---

## Routing

Routes are declared directly on controller methods using PHP attributes. There are no route files.

```php
use Bingo\Attributes\ApiController;use Bingo\Attributes\Delete;use Bingo\Attributes\Get;use Bingo\Attributes\Post;use Bingo\Attributes\Put;

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

> **Order matters.** Declare specific routes (`/search`, `/upload`) before wildcard routes (`/{id}`) within the same controller class, or the wildcard will match first.

**Available HTTP verb attributes:** `#[Get]` `#[Post]` `#[Put]` `#[Patch]` `#[Delete]` `#[Head]` `#[Options]`

For a catch-all: `#[Route('/path', 'METHOD')]`

List all registered routes at any time:

```bash
php bin/bingo show:routes
```

---

## Parameter Binding

Controller method parameters are resolved automatically using attributes:

```php
public function handle(
    #[Body]                     CreateUserDTO $dto,         // JSON body → validated DTO
    #[Param('id')]              int $id,                    // /users/{id} segment
    #[Query('page')]            int $page = 1,              // ?page=2
    #[Query('q')]               ?string $search = null,     // ?q=alice
    #[Headers('x-api-version')] ?string $version = null,   // request header
    #[Request]                  Request $request,           // raw Symfony Request
    #[UploadedFile('avatar')]   ?UploadedFile $file = null, // single file upload
    #[UploadedFiles]            array $files = [],          // all uploaded files
): Response
```

`#[Param]` and `#[Query]` values are automatically cast to the declared PHP type (`int`, `float`, `bool`, `string`). A missing non-nullable query param resolves to its default value.

---

## Middleware

### Global Middleware

Global middleware is registered in `bootstrap/app.php` and runs on every request:

```php
$app->use(App\Http\Middleware\AuthMiddleware::class)
    ->use(App\Http\Middleware\LogMiddleware::class);
```

Or pass an already-constructed instance if you need to configure it:

```php
$app->use(new CorsMiddleware(['allowed_origins' => ['https://myapp.com']]));
```

### Controller and Route Middleware

Use `#[Middleware]` on a controller class to protect every route in it, or on a method to target a single route. Both can be combined — class-level middleware always runs first.

```php
#[ApiController('/admin')]
#[Middleware([AuthMiddleware::class])]           // applies to all routes below
class AdminController
{
    #[Get('/dashboard')]
    public function dashboard(): Response {}     // AuthMiddleware runs

    #[Get('/logs')]
    #[Middleware([AuditLogMiddleware::class])]   // AuthMiddleware + AuditLogMiddleware
    public function logs(): Response {}
}
```

### Writing Custom Middleware

Implement `Bingo\Contracts\MiddlewareInterface`:

```php
use Bingo\Contracts\MiddlewareInterface;use Bingo\Http\Request;use Bingo\Http\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->headers->get('Authorization');

        if (!$token || !str_starts_with($token, 'Bearer ')) {
            throw new UnauthorizedException('Missing or invalid token');
        }

        $request->attributes->set('auth_token', substr($token, 7));

        return $next($request);      // call $next OUTSIDE any broad catch block
    }
}
```

> **Important:** Call `$next($request)` outside any `catch (\Throwable $e)` block. If you wrap it, you will swallow exceptions that belong to downstream middleware or the controller — including validation errors and HTTP exceptions.

### Built-in Middleware

The framework registers these automatically in development and production:

| Middleware | Behaviour |
|---|---|
| `CorsMiddleware` | Sets CORS headers; handles `OPTIONS` preflight. Wildcard in development, restricted to `CORS_ALLOWED_ORIGINS` in production. |
| `BodyParserMiddleware` | Parses JSON, form-encoded, and multipart request bodies. 10 MB limit in development, 1 MB in production. |
| `CompressionMiddleware` | Gzip-compresses responses larger than 1 KB when the client supports it. |
| `SecurityHeadersMiddleware` | Adds HSTS, CSP, `X-Frame-Options`, `X-Content-Type-Options`, and `X-XSS-Protection`. |
| `RequestIdMiddleware` | Generates a UUID v4 `X-Request-ID` for every request and echoes it in the response. |
| `RateLimitMiddleware` | Per-IP rate limiting (100 req/hour by default). Active in production only. |

---

## DTOs and Validation

Input DTOs extend `Bingo\Data\DataTransferObject` and declare validation constraints using Symfony Validator attributes. When a parameter is annotated with `#[Body]`, the framework fills the DTO from the request body and validates it automatically. Any failure returns **422** before your controller method is ever called.

```php
use Bingo\Data\DataTransferObject;use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDTO extends DataTransferObject
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public readonly string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    public readonly string $name;

    #[Assert\Range(min: 18, max: 120)]
    public readonly ?int $age = null;
}
```

Validation failure response (same shape as in [Exception Handling](#exception-handling)):

```json
{
  "statusCode": 422,
  "message": {
    "email": "This value is not a valid email address.",
    "age": "This value should be between 18 and 120."
  },
  "error": "Unprocessable Content"
}
```

**Output DTOs** are plain `readonly` value objects — no base class needed:

```php
final readonly class UserDTO
{
    public function __construct(
        public int     $id,
        public string  $name,
        public string  $email,
        public ?int    $age = null,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id:    $user->id,
            name:  $user->name,
            email: $user->email,
            age:   $user->age,
        );
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'age' => $this->age];
    }
}
```

Use `ApiResponse` to wrap responses with a consistent envelope:

```php
return Response::json(
    ApiResponse::success(data: UserDTO::fromModel($user), statusCode: 201)->toArray(),
    201,
);
```

```json
{
  "success": true,
  "message": "Success",
  "data": { "id": 1, "name": "Alice", "email": "alice@example.com" },
  "errors": null,
  "meta": null,
  "timestamp": "2026-03-30T12:00:00+00:00"
}
```

`ApiResponse` static factories: `::success()`, `::error()`, `::validation()`, `::notFound()`, `::unauthorized()`, `::forbidden()`

---

## Dependency Injection

The DI container automatically resolves concrete classes with typed constructors — no registration needed for the common case:

```php
#[ApiController('/users')]
class UsersController
{
    // UserService is resolved and injected automatically
    public function __construct(private readonly UserService $userService) {}
}
```

Register explicit bindings in `bootstrap/app.php` for interfaces or shared instances:

```php
// Interface → concrete (shared singleton)
$app->singleton(MailerInterface::class, SmtpMailer::class);

// Transient — fresh instance on every resolution
$app->bind(ReportBuilder::class);

// Pre-built object — bypasses the container entirely
$app->instance(PaymentConfig::class, new PaymentConfig(key: env('STRIPE_KEY')));
```

**Resolution order:** pre-built instances → registered singletons/bindings → reflection fallback.

`AppConfig` and `DatabaseConfig` are pre-registered by the framework and available for injection everywhere without any setup.

---

## Exception Handling

Throw HTTP exceptions anywhere in your service or controller — uncaught throwables are converted to a JSON response by the **exception handler** (comparable to a global `render` in Laravel or a framework-level exception layer).

### HTTP status codes (Symfony)

**Symfony HttpFoundation** already defines status codes on `Response` as `public const` (e.g. `Response::HTTP_NOT_FOUND`). `Bingo\Http\Response` extends Symfony’s `Response`, so use:

```php
use Bingo\Exceptions\Http\HttpException;use Bingo\Http\Response;

throw new HttpException(Response::HTTP_FORBIDDEN, 'You cannot do that');
```

`HttpException::phraseForStatusCode()` and default JSON **`error`** text use Symfony’s **`Response::$statusTexts`** (IANA-style reason phrases), so you stay aligned with the HTTP component instead of duplicating magic numbers or labels.

### Built-in HTTP exception classes

These live under `Bingo\Exceptions\Http\` (folder `core/Bingo/Exceptions/Http/`). They are thin subclasses of `HttpException` with the correct status preset. Optional **third constructor argument** `?string $description` overrides the JSON **`error`** field when you want a custom short label.

| Class | Status |
|-------|--------|
| `BadRequestException` | 400 |
| `UnauthorizedException` | 401 |
| `ForbiddenException` | 403 |
| `NotFoundException` | 404 |
| `MethodNotAllowedException` | 405 |
| `NotAcceptableException` | 406 |
| `RequestTimeoutException` | 408 |
| `ConflictException` | 409 |
| `GoneException` | 410 |
| `PreconditionFailedException` | 412 |
| `PayloadTooLargeException` | 413 |
| `UnsupportedMediaTypeException` | 415 |
| `ImATeapotException` | 418 |
| `UnprocessableEntityException` | 422 |
| `TooManyRequestsException` | 429 |
| `InternalServerErrorException` | 500 |
| `NotImplementedException` | 501 |
| `BadGatewayException` | 502 |
| `ServiceUnavailableException` | 503 |
| `GatewayTimeoutException` | 504 |
| `HttpVersionNotSupportedException` | 505 |

DTO validation failures still use **`ValidationException`** → **422** with `message` as a field map (see below).

```php
use Bingo\Exceptions\Http\BadRequestException;use Bingo\Exceptions\Http\ConflictException;use Bingo\Exceptions\Http\ForbiddenException;use Bingo\Exceptions\Http\NotFoundException;use Bingo\Exceptions\Http\UnauthorizedException;

throw new NotFoundException('User not found');          // 404
throw new UnauthorizedException();                      // 401
throw new ConflictException('Email already exists');    // 409
throw new ForbiddenException('Insufficient scope');     // 403
throw new BadRequestException('Invalid payload');       // 400

// Custom JSON "error" (third argument)
throw new BadRequestException('Something bad happened', null, 'Some error description');
```

### Default JSON shape

HTTP errors use a small, flat body: `statusCode`, `message`, and `error` (short reason phrase). The HTTP status line matches `statusCode`.

```json
{
  "statusCode": 404,
  "message": "User not found",
  "error": "Not Found"
}
```

Validation failures on `#[Body]` DTOs return **422** with `message` as a **field → message** object. The **`error`** string is Symfony’s reason phrase for **422** (see `Response::$statusTexts`, e.g. *Unprocessable Content*).

```json
{
  "statusCode": 422,
  "message": {
    "email": "This value is not a valid email address."
  },
  "error": "Unprocessable Content"
}
```

In **debug mode** (`APP_DEBUG=true`), uncaught non-HTTP exceptions use `message` with the real exception text and add a `details` object (`exception`, `file`, `line`, `trace`). In production, `message` and `error` are generic and `details` is omitted.

### Custom exception handler

**Where to put your code:** When `core/` is installed as a separate Composer package, you still **never** edit vendor/core. Implement `Bingo\Contracts\ExceptionHandlerInterface` under your application — for example [`app/Exceptions/Handler.php`](app/Exceptions/Handler.php) — and **register** it from [`bootstrap/app.php`](bootstrap/app.php). The template `Handler` delegates to the framework default until you change its `handle()` method.

**Option A — app class instance (highest priority):**

```php
// bootstrap/app.php, after Application::create()
$app->exceptionHandler(new \App\Exceptions\Handler($app->isDebug()));
```

**Option B — anonymous / one-off:**

```php
use Bingo\Contracts\ExceptionHandlerInterface;use Bingo\Http\Response;

$app->exceptionHandler(new class implements ExceptionHandlerInterface {
    public function handle(\Throwable $e): Response
    {
        return Response::json(['ok' => false, 'reason' => $e->getMessage()], 500);
    }
});
```

**Option C — container binding** (resolved after `compile()`; use when your handler needs injected services — skipped if you also call `$app->exceptionHandler(...)` with an instance):

```php
$app->singleton(\Bingo\Contracts\ExceptionHandlerInterface::class, \App\Exceptions\Handler::class);
```

If you bind the class name only, ensure `App\Exceptions\Handler` has a constructor the container can satisfy (e.g. inject `Config\AppConfig` for debug instead of a raw bool), or register a factory with `$app->instance(...)`.

Priority: **`$app->exceptionHandler($instance)`** → **container binding** for `ExceptionHandlerInterface` → **built-in** `Bingo\Exceptions\ExceptionHandler`.

---

## Eloquent ORM

Standard Laravel Eloquent models work out of the box:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email', 'age', 'bio'];
    protected $hidden   = ['password'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
```

Generate a model scaffold with the CLI:

```bash
php bin/bingo generate:model Post
```

---

## CLI — bin/bingo

Bingo includes a full-featured CLI powered by Symfony Console. Commands use a `namespace:verb` style with short aliases for common ones.

```bash
php bin/bingo <command> [options] [arguments]
php bin/bingo list          # show all available commands
```

### Development Server

```bash
php bin/bingo serve
php bin/bingo serve --host=0.0.0.0 --port=9000
```

The server command prints registered routes at boot, then logs each request with color-coded HTTP methods and status codes.

```
 [Bingo] 12345  - 03/30/2026, 12:00:00 PM   LOG    [Kernel]          Starting Bingo application...
 [Bingo] 12345  - 03/30/2026, 12:00:00 PM   LOG    [RouterExplorer]  Mapped {GET /users} route
 [Bingo] 12345  - 03/30/2026, 12:00:00 PM   LOG    [RouterExplorer]  Mapped {POST /users} route
 [Bingo] 12345  - 03/30/2026, 12:00:00 PM   LOG    [Server]          Application is running on: http://127.0.0.1:8000
 [Bingo] 12345  - 03/30/2026, 12:00:01 PM   LOG    [HTTP]            GET     /users  → 200
```

### Database Migrations

```bash
php bin/bingo db:migrate        # alias: db:m
```

Runs every `.php` file in `database/migrations/` in alphabetical order. Files are sorted by name, so timestamp-prefixed filenames run in the correct sequence:

```
database/migrations/
├── 2026_01_01_000000_create_users_table.php
└── 2026_01_02_000000_create_posts_table.php
```

Each migration file receives an already-booted Eloquent/Capsule instance — just write schema operations directly:

```php
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

if (!Capsule::schema()->hasTable('posts')) {
    Capsule::schema()->create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content');
        $table->foreignId('user_id')->constrained('users');
        $table->timestamps();
    });
}
```

### Code Generators

All generator commands write a scaffold to the correct directory and print the file path. Use the short alias for speed.

| Command | Alias | Output |
|---|---|---|
| `generate:controller <Name>` | `g:controller` | `app/Http/Controllers/NameController.php` |
| `generate:service <Name>` | `g:service` | `app/Services/NameService.php` |
| `generate:middleware <Name>` | `g:middleware` | `app/Http/Middleware/NameMiddleware.php` |
| `generate:exception <Name>` | `g:exception` | `app/Exceptions/NameException.php` (extends `HttpException`; use `--status`) |
| `generate:model <Name>` | `g:model` | `app/Models/Name.php` |
| `generate:migration <name>` | `g:migration` | `database/migrations/YYYY_MM_DD_HHiiss_name.php` |
| `generate:command <Name>` | `g:command` | `app/Console/Commands/NameCommand.php` |

Examples:

```bash
php bin/bingo g:controller Post
php bin/bingo g:service    PostPublisher
php bin/bingo g:model      Post
php bin/bingo g:migration  create_posts_table
php bin/bingo g:middleware RateLimit
php bin/bingo g:exception PaymentRequired --status=402
```

The migration generator infers the table name from the migration name — `create_posts_table` produces a stub that creates the `posts` table.

### Custom Commands

Generate a command scaffold:

```bash
php bin/bingo g:command SendWelcomeEmails
# Creates: app/Console/Commands/SendWelcomeEmailsCommand.php
```

The generated class is a standard Symfony Console command. Constructor dependencies are resolved through the DI container, so you can inject services directly:

```php
namespace App\Console\Commands;

use App\Services\MailService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendWelcomeEmailsCommand extends Command
{
    public function __construct(private readonly MailService $mail)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('app:send-welcome-emails')
             ->setDescription('Send welcome emails to all new users');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->mail->sendWelcome();
        $output->writeln('<info>Done.</info>');
        return Command::SUCCESS;
    }
}
```

Register it in `bootstrap/console.php`:

```php
$kernel->command(\App\Console\Commands\SendWelcomeEmailsCommand::class);
```

Run it:

```bash
php bin/bingo app:send-welcome-emails
```

---

## Testing

```bash
composer test
vendor/bin/phpunit --filter ContainerTest   # run a specific test class
```

Tests mirror the application structure:

```
tests/
├── Unit/
│   ├── Bingo/
│   │   ├── Container/
│   │   ├── Http/
│   │   ├── Router/
│   │   └── ...
│   └── App/
│       └── DTOs/
└── Stubs/
    ├── Controllers/
    └── Services/
```

---

## License

MIT — [Bijaya Prasad Kuikel](https://github.com/sadhakbj)
