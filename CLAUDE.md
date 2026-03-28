# Bingo Framework - CLAUDE.md

## Project Overview

**Bingo** is a custom PHP 8.3+ API framework built from scratch by Bijaya Prasad Kuikel (`sadhakbj`).
Inspired by NestJS (attribute-based routing, DTOs, controllers) and Laravel (Eloquent ORM, `.env`, bootstrap pattern).
Wraps Symfony HTTP/Routing components — does NOT use Laravel or Symfony's full framework.

## Key Architecture

- **Entry point:** `public/index.php` → `bootstrap/app.php` → `Application::run()`
- **Namespaces:** `Core\` → `core/`, `App\` → `app/` (PSR-4 via composer)
- **ORM:** Laravel Eloquent (`illuminate/database ^13`) with SQLite (`database/database.sqlite`)
- **HTTP primitives:** Symfony HttpFoundation (Request/Response)
- **Routing:** Symfony Routing (UrlMatcher + RouteCollection), registered via PHP 8 Reflection at bootstrap time
- **Validation:** Symfony Validator (attribute-based constraints on DTOs)
- **CLI:** `php bin/framework show:routes` (Symfony Console)

## Request Lifecycle

```
public/index.php
  → bootstrap/app.php (registers controllers, sets up DB)
  → Application::run()
  → MiddlewarePipeline::process()  [CORS → BodyParser → Compression → SecurityHeaders → RequestId → (RateLimit in prod)]
  → Router::dispatch()
  → PHP Reflection resolves method params via attributes (#[Body], #[Query], #[Param], etc.)
  → Controller method called
  → Response sent back through pipeline
```

## Important Files

| File | Purpose |
|---|---|
| `public/index.php` | Web entry point |
| `bootstrap/app.php` | App factory, DB setup, controller registration |
| `core/Application.php` | App class, env loading, factory methods (`::create()`, `::development()`, `::production()`) |
| `core/Router/Router.php` | Route registration via reflection + dispatch + param injection |
| `core/Http/Middleware/MiddlewarePipeline.php` | Express-style `$next` middleware chain |
| `core/Data/DataTransferObject.php` | Abstract DTO base (fill, validate, toArray, toJson) |
| `core/DTOs/Http/ApiResponse.php` | Standard API response envelope DTO |
| `core/Database/Database.php` | Singleton Eloquent Capsule setup |
| `app/Http/Controllers/UsersController.php` | Full demo of all framework features |
| `app/Services/UserService.php` | Business logic layer |
| `database/migrate.php` | Manual migration runner |

## PHP 8 Attribute System

### Class-level
- `#[ApiController('/prefix')]` — marks API controller, enforces `Response` return type

### Method-level (HTTP verbs)
- `#[Get('/path')]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]`, `#[Head]`, `#[Options]`
- `#[Route('/path', 'METHOD')]` — generic
- `#[Middleware([Class::class])]` — attach middleware to route

### Parameter-level (auto-resolved by Router)
- `#[Body]` — fills + validates a DTO from request body (throws 422 on failure)
- `#[Query('key')]` — query string param with type casting
- `#[Param('key')]` — route path param with type casting
- `#[Headers('key')]` — HTTP header value
- `#[Request]` — injects raw `Request` object
- `#[UploadedFile('key')]` — single file upload
- `#[UploadedFiles]` — all uploaded files

## DTO System

- `DataTransferObject` (abstract base) — all DTOs extend this
- `readonly` properties preferred for immutability
- `fromRequest($request)` — fills from body + auto-validates (throws `ValidationException` → 422)
- `validate()` — runs Symfony Validator constraints
- `toArray()`, `toJson()`, `only([...])`, `except([...])`, `has()`, `get()`
- `DTOCollection` — iterable/filterable collection of DTOs

**App DTOs:**
- `App\DTOs\CreateUserDTO` — input DTO with validation constraints
- `App\DTOs\User\UserDTO` — output DTO with computed helpers
- `Core\DTOs\Http\ApiResponse` — response envelope (use `ApiResponse::success()`, `::error()`, `::notFound()`, etc.)

## Built-in Global Middleware (core/Http/Middleware/)

| Class | Purpose |
|---|---|
| `CorsMiddleware` | CORS headers; dev=wildcard, prod=restricted |
| `BodyParserMiddleware` | JSON/form/multipart body parsing; 10MB dev / 1MB prod limit |
| `CompressionMiddleware` | Gzip for responses >1024 bytes |
| `SecurityHeadersMiddleware` | HSTS, CSP, X-Frame-Options, X-XSS-Protection, etc. |
| `RequestIdMiddleware` | UUID4 `X-Request-ID` on every request/response |
| `RateLimitMiddleware` | In-memory 100 req/hr per IP (prod only) |

## Models & Database

- Models in `app/Models/` — use Eloquent with PHP 8 attributes (`#[Table]`, `#[Fillable]`, `#[Hidden]`)
- `User` hasMany `Post`; `Post` belongsTo `User`
- Run migrations: `php database/migrate.php`
- **Known bug:** `database/migrate.php` references old namespace `Framework\Database\Database` — should be `Core\Database\Database`

## App Controllers

- `HomeController` — non-API, plain routes, can return strings or `Response::json()`
- `UsersController` — full `#[ApiController('/users')]` demo, all routes must return `Response`

## Environment / Config

`.env` (based on `.env.example`):
```
APP_ENV=development
APP_DEBUG=true
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=3600
```

Access via `$_ENV['KEY']` or `getenv('KEY')`. `.env` is optional (uses `safeLoad()`).

## Dependencies (composer.json)

**Runtime:** `php ^8.3`, `illuminate/database ^13`, `symfony/http-foundation ^8`, `symfony/routing ^8`, `symfony/console ^8`, `symfony/validator ^8`, `vlucas/phpdotenv ^5.5`

**Dev:** `symfony/var-dumper`

## Known Limitations / TODOs (from dev plan)

- **No DI container** — controllers/services are manually `new`'d
- **Route-level middleware** runs old-style (`handle()` returns bool), NOT through `$next` pipeline — cannot modify response
- **RateLimitMiddleware** uses PHP static memory, not Redis/APCu — resets per process
- **No auto-discovery** of controllers — must register every class in `bootstrap/app.php`
- **`UserService::emailExists()` and `persistUser()`** are hardcoded stubs — not real DB calls yet
- **No PHPUnit** — `tests/` are demo scripts only
- **`app/Http/Middleware/AuthMiddleware.php` and `LogMiddleware.php`** are empty stubs
- **Phase roadmap** (6 phases): DI container, Modules, Guards/Interceptors, OpenAPI, Event system, WebSockets — most are not yet built

## Development Workflow

```bash
# Start dev server
php -S localhost:8000 -t public

# Run migrations
php database/migrate.php

# Show routes
php bin/framework show:routes

# Install dependencies
composer install
```

## Code Conventions

- PHP 8.3+ features expected (readonly, named args, attributes, match, enums welcome)
- All framework core code lives in `core/` under `Core\` namespace
- All app code lives in `app/` under `App\` namespace
- API controllers return `Response` objects (enforced by `#[ApiController]`)
- Use `ApiResponse` DTO for consistent JSON envelope: `ApiResponse::success($data)`, `::error($msg, $code)`
- DTOs should be `readonly` and validated at boundaries
- Follow existing NestJS-style structure: Controller → Service → Model
