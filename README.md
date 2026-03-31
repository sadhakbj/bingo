# Bingo

> A PHP 8.5+ framework for API-first development — attribute-driven routing, typed configuration, automatic dependency injection, and Laravel Eloquent ORM, with zero boilerplate.

Built from scratch on top of Symfony's HTTP, Routing, Validator, Console, and DI components, with attribute-driven routing and a familiar `app/` layout.

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
  - [Response metadata (status & headers)](#response-metadata-status--headers)
- [Parameter Binding](#parameter-binding)
- [Server-Sent Events (SSE)](#server-sent-events-sse)
- [Middleware](#middleware)
  - [Global Middleware](#global-middleware)
  - [Controller and Route Middleware](#controller-and-route-middleware)
  - [Writing Custom Middleware](#writing-custom-middleware)
  - [Built-in Middleware](#built-in-middleware)
- [Rate Limiting](#rate-limiting)
  - [Global Rate Limiting](#global-rate-limiting)
  - [Per-Route Throttling](#per-route-throttling)
  - [Storage Backends](#storage-backends)
  - [Using RateLimiter Directly](#using-ratelimiter-directly)
  - [Response Headers](#response-headers)
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
# Rate limiting is configured in bootstrap/app.php, not via env vars.
# Default (production only): 1 000 requests per minute per IP.
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
use Bingo\Attributes\Route\ApiController;use Bingo\Attributes\Route\Delete;use Bingo\Attributes\Route\Get;use Bingo\Attributes\Route\Post;use Bingo\Attributes\Route\Put;

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

### Response metadata (status & headers)

The router inspects **`#[HttpCode]`** and **`#[Header]`** on the **controller class** and the **matched action method** *after* your code returns. They work the same for `#[ApiController]` routes and plain `#[Route]` actions.

**`#[HttpCode]`**

- Sets the response status only when it is still the default **200** (e.g. you returned `Response::json($data)` without a second argument).
- If you already set a status in code (`Response::json($data, 201)`, streamed responses with their own code, etc.), the attribute is **not** applied.
- On a method, use **one** `#[HttpCode]`. For a default status on every action in a class, put `#[HttpCode]` on the **class**; a `#[HttpCode]` on a **method** overrides that for that action only (method is read first, then class).

**`#[Header]`**

- Adds outgoing headers. Use **`Attribute::IS_REPEATABLE`**: stack several `#[Header('Name', 'value')]` lines on the same method or class.
- **Class** headers are merged first, then **method** headers; the same header **name** on the method **replaces** the class value.
- If the **`Response` already has that header name** when the action returns, the attribute does **nothing** for that name (your code wins).

```php
use Bingo\Attributes\Route\Get;
use Bingo\Attributes\Route\Header;
use Bingo\Attributes\Route\HttpCode;
use Bingo\Http\Response;

#[Get('/jobs')]
#[HttpCode(202)]
#[Header('X-Request-Id', 'queued')]
public function queue(): Response
{
    return Response::json(['status' => 'queued']);
}
```

Class-level default headers with per-route status and header overrides:

```php
use Bingo\Attributes\Route\ApiController;
use Bingo\Attributes\Route\Get;
use Bingo\Attributes\Route\Header;
use Bingo\Attributes\Route\HttpCode;
use Bingo\Http\Response;

#[ApiController('/reports')]
#[Header('X-API-Version', '1')]
class ReportsController
{
    #[Get('/export')]
    #[HttpCode(202)]
    #[Header('X-API-Version', '2')] // replaces class header for this route only
    public function export(): Response
    {
        return Response::json(['state' => 'processing']);
    }
}
```

> **Request vs response:** `#[Headers]` on a **parameter** reads a **request** header into the action. `#[Header]` on the **method or class** sets a **response** header. Different attributes.

To verify behaviour, hit your own route with `curl -i` or a client that shows status and response headers.

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

## Server-Sent Events (SSE)

**Server-Sent Events** let the **server push** text to the browser over a single HTTP response (`Content-Type: text/event-stream`). The client uses **`EventSource`** (GET, automatic reconnect). Use it for one-way updates (feeds, progress, streamed LLM output); use **WebSockets** when you need a full duplex channel.

In Bingo, **`Response::eventStream()`** takes a callable that returns a **generator** (or any iterable). Each **`yield`** emits one event. Use **`StreamedEvent`** for a named `event:` line; a plain **`yield $array`** uses the default `message` event. When the generator finishes, Bingo sends a final **`</stream>`**-style frame on `message` so you can **`close()`** the `EventSource` (override with the second argument to `eventStream()` if you want). For a raw chunked body without SSE framing, use **`Response::stream()`**. Controllers return **`Bingo\Http\StreamedResponse`**.

```php
use Bingo\Http\Response;use Bingo\Http\Sse\StreamedEvent;use Bingo\Http\StreamedResponse;

#[Get('/notifications/stream')]
public function stream(): StreamedResponse
{
    return Response::eventStream(function (): \Generator {
        yield new StreamedEvent('update', ['status' => 'ok']);
    });
}
```

```javascript
const es = new EventSource('/notifications/stream');
es.addEventListener('update', (e) => console.log(JSON.parse(e.data)));
es.onmessage = (e) => { if (e.data === '</stream>') es.close(); };
```

`EventSource` does not send custom headers; sort **CORS** and auth (cookies / query) like any GET. Behind **nginx**, you may need **`proxy_buffering off`** on that location; Bingo sets **`X-Accel-Buffering: no`**.

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
| `CompressionMiddleware` | Gzip-compresses responses larger than 1 KB when the client supports it. Skips streamed bodies (`Bingo\Http\StreamedResponse` / SSE) and `text/event-stream` so chunks are not buffered or gzipped. |
| `SecurityHeadersMiddleware` | Adds HSTS, CSP, `X-Frame-Options`, `X-Content-Type-Options`, and `X-XSS-Protection`. |
| `RequestIdMiddleware` | Generates a UUID v4 `X-Request-ID` for every request and echoes it in the response. |
| `RateLimitMiddleware` | Sliding-window per-IP rate limiting. Active in production only (default: 1 000 req/min). Fully configurable — see [Rate Limiting](#rate-limiting). |

---

## Rate Limiting

Bingo ships a first-class rate limiter built on a **sliding-window counter** algorithm and a swappable storage backend. It is available as a global production middleware, a per-route `#[Throttle]` attribute, and an injectable `RateLimiter` service you can call directly from anywhere.

### Global Rate Limiting

In production, `RateLimitMiddleware` is active automatically at **1,000 requests per minute per IP** — enough headroom that a normal user never notices it, but enough to put a ceiling on bots and scrapers (~16 req/s sustained). To tighten or loosen it, register it explicitly in `bootstrap/app.php` before `return $app`:

```php
use Bingo\Http\Middleware\RateLimitMiddleware;

// 60 requests per minute per IP (replaces the production default)
$app->use(RateLimitMiddleware::perMinute(60));

// 500 requests per hour per IP
$app->use(RateLimitMiddleware::perHour(500));

// Fully configured — custom window and key resolver
$app->use(RateLimitMiddleware::create(
    limit:         200,
    windowSeconds: 60,
    keyResolver:   fn($request) => $request->headers->get('X-API-Key') ?? $request->getClientIp(),
));
```

### Per-Route Throttling

Use `#[Throttle]` on a controller class or method to apply an independent rate limit to that route. Class-level and method-level throttles are additive — each creates its own bucket keyed to `{routeName}:{clientIp}`.

```php
use Bingo\Attributes\Route\Throttle;

// All routes in this controller: 1 000 req/hour per IP
#[ApiController('/api')]
#[Throttle(requests: 1000, per: 3600)]
class ApiController
{
    // Inherits the class throttle (1 000/hour)
    #[Get('/feed')]
    public function feed(): Response {}

    // Tighter limit for an expensive endpoint — both throttles apply independently
    #[Get('/export')]
    #[Throttle(requests: 10, per: 60)]
    public function export(): Response {}
}
```

`#[Throttle]` is repeatable, so you can stack multiple windows on the same route:

```php
#[Get('/search')]
#[Throttle(requests: 5,   per: 1)]    // burst: 5 per second
#[Throttle(requests: 100, per: 60)]   // sustained: 100 per minute
public function search(): Response {}
```

### Storage Backends

The storage backend is bound to `RateLimiterStore` in the DI container. Swap it in `bootstrap/app.php` to change where counters are persisted.

#### `InMemoryStore` (default)

Counters live in a PHP static array — shared across all instances in the same worker process, but **not** across workers or server restarts. Zero configuration.

```php
// This is the default — no action needed.
// Explicitly binding it looks like:
use Bingo\RateLimit\Store\InMemoryStore;
use Bingo\RateLimit\Contracts\RateLimiterStore;

$app->bind(RateLimiterStore::class, InMemoryStore::class);
```

#### `FileStore`

Counters are written to JSON files under a directory of your choosing. Counts survive process restarts, making this suitable for **single-server production** deployments without external infrastructure.

```php
use Bingo\RateLimit\Store\FileStore;
use Bingo\RateLimit\Contracts\RateLimiterStore;

$app->instance(
    RateLimiterStore::class,
    new FileStore(base_path('storage/rate-limit')),
);
```

#### Custom backend (Redis, Memcached, DynamoDB, …)

Implement `RateLimiterStore` with its three methods and bind it:

```php
use Bingo\RateLimit\Contracts\RateLimiterStore;

class RedisRateLimiterStore implements RateLimiterStore
{
    public function __construct(private \Redis $redis) {}

    public function increment(string $key, int $windowId, int $decaySeconds): int
    {
        $storageKey = $key . ':' . $windowId;
        $count      = $this->redis->incr($storageKey);
        if ($count === 1) {
            $this->redis->expire($storageKey, $decaySeconds * 2);
        }
        return $count;
    }

    public function count(string $key, int $windowId): int
    {
        return (int) ($this->redis->get($key . ':' . $windowId) ?: 0);
    }

    public function reset(string $key): void
    {
        foreach ($this->redis->keys($key . ':*') as $k) {
            $this->redis->del($k);
        }
    }
}

// bootstrap/app.php
$app->instance(RateLimiterStore::class, new RedisRateLimiterStore($redis));
```

### Using RateLimiter Directly

Inject `RateLimiter` into any service for custom logic — login attempt throttling, OTP verification, email send caps:

```php
use Bingo\RateLimit\RateLimiter;

class AuthService
{
    public function __construct(private readonly RateLimiter $limiter) {}

    public function login(string $email, string $password): User
    {
        $key    = 'login:' . $email;
        $result = $this->limiter->attempt($key, limit: 5, windowSeconds: 900);

        if ($result->isDenied()) {
            throw new TooManyRequestsException(result: $result);
        }

        $user = User::where('email', $email)->first();

        if (!$user || !password_verify($password, $user->password)) {
            throw new UnauthorizedException('Invalid credentials');
        }

        // Reset the counter on successful login
        $this->limiter->clear($key);

        return $user;
    }
}
```

`RateLimiter` public API:

```php
$limiter->attempt(string $key, int $limit, int $windowSeconds): RateLimitResult
$limiter->tooManyAttempts(string $key, int $limit, int $windowSeconds): bool  // read-only check
$limiter->clear(string $key): void
```

### Response Headers

Every response that passes through `RateLimitMiddleware` (or a `#[Throttle]` route) includes these headers. On a `429` response, `Retry-After` is also set (RFC 6585).

| Header | Description |
|---|---|
| `X-RateLimit-Limit` | The maximum number of requests allowed in the window |
| `X-RateLimit-Remaining` | Estimated requests still available in the current window |
| `X-RateLimit-Reset` | Unix timestamp at which the window resets |
| `Retry-After` | Seconds to wait before retrying (only on `429` responses) |

### Algorithm

Bingo uses a **sliding-window counter** rather than a fixed window. A fixed window resets on a hard boundary, which lets a client send 2× the limit by bursting at the end of one window and the start of the next.

The sliding window weights the previous window's count by how far through the current window we are:

```
estimated = prev_count × (1 − elapsed_fraction) + curr_count
```

If `estimated ≥ limit` the request is denied; otherwise the counter increments. This smooths out bursts with O(1) space per key — the same approach used by Cloudflare and Redis Cell.

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
