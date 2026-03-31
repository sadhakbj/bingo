# Bingo Framework — CLAUDE.md

Concise context for AI assistants working in this repo. Authoritative user docs: **README.md**.

## Project overview

**Bingo** is a PHP **8.5+** API-first framework by Bijaya Prasad Kuikel (`sadhakbj`). It uses **attribute routing and DTOs**, Laravel **Eloquent** and a familiar `app/` layout, and **Symfony components** (HTTP Foundation, Routing, Validator, Console, DependencyInjection). It does **not** ship Laravel or the full Symfony Framework.

## Architecture

| Layer | Detail |
|--------|--------|
| **HTTP entry** | `public/index.php` → `bootstrap/app.php` → `Application::run()` |
| **Namespaces** | `Bingo\` → `core/Bingo/`, `App\` → `app/`, `Config\` → `config/` (PSR-4) |
| **ORM** | Illuminate Database / Eloquent (`illuminate/database ^13`); default SQLite path `database/database.sqlite` |
| **HTTP** | Symfony HttpFoundation via `Bingo\Http\Request` / `Response`; use `Response::HTTP_*` constants for status codes |
| **Routing** | Symfony `RouteCollection` + `UrlMatcher`; routes registered by **reflection** on controllers at bootstrap |
| **Validation** | Symfony Validator on DTO properties |
| **DI** | `Bingo\Container\Container` wraps **Symfony ContainerBuilder** + reflection fallback autowiring |
| **Config** | Typed classes in `config/` wired by `Bingo\Config\ConfigLoader` + `#[Env]` |
| **CLI** | Symfony Console via `php bin/bingo` (`bootstrap/console.php`) |

## Request lifecycle

```
public/index.php
  → bootstrap/app.php (optional DI bindings, controller registration)
  → Application::run()
  → container->compile()          ← Symfony DI freeze; register services before this
  → Request::createFromGlobals()
  → MiddlewarePipeline::process()
        Cors → BodyParser → Compression → SecurityHeaders → RequestId
        → RateLimitMiddleware (production only)
        → Router::dispatch()
              route #[Middleware] runs in a nested pipeline ($next)
              reflection resolves #[Body], #[Query], #[Param], …
  → Response::send()
```

Uncaught throwables in `Application::handle()` become JSON via `Bingo\Contracts\ExceptionHandlerInterface` (default: `Bingo\Exceptions\ExceptionHandler`). **Application-owned** overrides live under `app/` (e.g. `App\Exceptions\Handler`) and are registered in `bootstrap/app.php` — do not edit `core/` when it is a Composer package.

## Important files

| Path | Role |
|------|------|
| `public/index.php` | Web front controller |
| `bootstrap/app.php` | HTTP app instance, controllers, optional `singleton` / `bind` / `instance` |
| `bootstrap/console.php` | CLI kernel; `require`s same `app.php` (no `run()`) |
| `core/Bingo/Application.php` | Env, config, DB boot, pipeline, container proxies |
| `core/Bingo/Container/Container.php` | PSR-11 + `singleton` / `bind` / `instance` / `compile()` |
| `core/Bingo/Http/Router/Router.php` | Attribute discovery, dispatch, param binding, route middleware |
| `core/Bingo/Http/Middleware/MiddlewarePipeline.php` | Global + per-route `$next` chain |
| `core/Bingo/Config/ConfigLoader.php` | `#[Env]` → constructor or properties |
| `core/Bingo/Contracts/ExceptionHandlerInterface.php` | Pluggable Throwable → `Response` |
| `core/Bingo/Exceptions/ExceptionHandler.php` | Default JSON errors (`statusCode`, `message`, `error`; phrases from Symfony `Response::$statusTexts`) |
| `core/Bingo/Exceptions/Http/*Exception.php` | Built-in HTTP exception subclasses (`Bingo\Exceptions\Http\`) |
| `core/Bingo/Data/DataTransferObject.php` | Input DTO base (`fromRequest`, validate, `toArray`) |
| `core/Bingo/DTOs/Http/ApiResponse.php` | JSON envelope helpers |
| `core/Bingo/Database/Database.php` | Eloquent Capsule setup from `DatabaseConfig` |
| `app/Exceptions/Handler.php` | Optional `ExceptionHandlerInterface`; customize error JSON here |
| `app/Http/Controllers/UsersController.php` | Sample `#[ApiController]` app |
| `app/Http/Controllers/HomeController.php` | Sample `#[Route]` app |
| `app/Services/UserService.php` | Service layer (Eloquent-backed) |

**Migrations:** `php bin/bingo db:migrate` (alias `db:m`). Migration PHP files live in `database/migrations/`. There is **no** `database/migrate.php` in current workflow.

## PHP 8 attributes

**Class:** `#[ApiController('/prefix')]` — API controllers must return `Response`.

**HTTP verbs:** `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]`, `#[Head]`, `#[Options]`, or `#[Route('/path', 'METHOD')]`.

**Controller / method:** `#[Middleware([SomeMiddleware::class])]` — class-level first, then method-level; both use the real middleware pipeline.

**Parameters:** `#[Body]`, `#[Query('key')]`, `#[Param('key')]`, `#[Headers('key')]`, `#[Request]`, `#[UploadedFile('key')]`, `#[UploadedFiles]`.

**Response metadata (method or class):** `#[HttpCode]` and `#[Header]` — applied by the router after the action returns; rules and examples in README → *Response metadata (status & headers)*.

## DTOs

- Input DTOs extend `DataTransferObject`; use Symfony `Assert\*` on properties.
- `fromRequest()` fills + validates; API controllers throw `ValidationException` → **422** with a field map in `message`.
- Output DTOs can be plain `readonly` classes (e.g. `App\DTOs\User\UserDTO`).
- Use `ApiResponse::success()` (and related) for **success** response envelopes in controllers; framework **errors** use the default exception handler format unless you replace it.

## Built-in global middleware

`CorsMiddleware`, `BodyParserMiddleware`, `CompressionMiddleware`, `SecurityHeadersMiddleware`, `RequestIdMiddleware`, `RateLimitMiddleware` (prod). Rate limiting is **in-process static storage** — per worker, not distributed; fine for demos, not a cluster-wide guarantee.

## Models

Eloquent models in `app/Models/` (e.g. Illuminate `#[Table]`, `#[Fillable]`, `#[Hidden]`). Example: `User` hasMany `Post`.

## Environment

See **README.md** for full `.env` reference. `Dotenv::safeLoad()` — missing `.env` is OK.

**Dependencies (runtime):** `php ^8.5`, `illuminate/database`, Symfony `http-foundation`, `routing`, `console`, `validator`, `dependency-injection`, `vlucas/phpdotenv`.

**Dev:** `phpunit/phpunit`, `symfony/var-dumper`.

## Known limitations (accurate)

- **No controller auto-discovery** — register classes in `bootstrap/app.php` (`$app->controllers([...])`).
- **Rate limit** — memory-only unless you replace middleware or add an external gateway.
- **Success vs error JSON** — errors use the default `ExceptionHandler` shape; successes may still use `ApiResponse` or raw arrays.
- **Unknown routes / wrong method** — `Router` throws `NotFoundException` / `MethodNotAllowedException`; `Application` maps them through `ExceptionHandler` (JSON envelope). Use an explicit `#[ApiController('/api')]` prefix to keep API routes grouped; empty prefix is still fine for route paths.
- **Roadmap features** not built yet: OpenAPI generation, formal modules/guards, queues, etc.

## Development commands

```bash
composer install
php bin/bingo serve              # dev server + route log
php bin/bingo show:routes
php bin/bingo db:migrate
php bin/bingo g:exception Name   # app/Exceptions (optional --status=4xx)
composer test                    # PHPUnit
```

## Conventions

- `declare(strict_types=1);` in PHP files.
- `Bingo\` = framework; `App\` = application.
- API controller methods return `Response`; use `ApiResponse` for envelopes where possible.
- Controller → Service → Model for non-trivial logic.
