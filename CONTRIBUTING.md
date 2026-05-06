# Contributing to Bingo

Thank you for your interest in Bingo. This document covers the project's current state, architecture, conventions, and how to contribute effectively.

---

## Table of Contents

- [Project Philosophy](#project-philosophy)
- [Current State (v1.0)](#current-state-v10)
- [Architecture Overview](#architecture-overview)
- [Setting Up Locally](#setting-up-locally)
- [Codebase Map](#codebase-map)
- [Stability Matrix](#stability-matrix)
- [Known Limitations](#known-limitations)
- [Roadmap](#roadmap)
- [Contribution Guidelines](#contribution-guidelines)
- [Code Standards](#code-standards)
- [Testing](#testing)

---

## Project Philosophy

Bingo is an **API-first PHP microframework** that prioritises three things:

1. **No boilerplate.** Routes, middleware, parameter binding, and validation are all declared with PHP attributes. No route files. No manual wiring of request data.
2. **Familiar but independent.** The developer experience is inspired by NestJS (attribute-driven, CLI generators) and Laravel (Eloquent, `app/` layout). The framework itself depends on Symfony HTTP Foundation, Routing, Console, Validator, and DI components — not on Laravel or the full Symfony Framework.
3. **Correct by default.** Typed configuration, strict types throughout, a proper exception hierarchy, and a structured response envelope mean that common mistakes fail loudly and early.

Bingo is **not** trying to be a full-stack framework. It has no view layer, no queue system, no event bus, and no frontend scaffolding. It is designed to be the foundation for REST APIs and microservices.

---

## Current State (v1.0)

Bingo is **feature-complete for a v1.0 API microframework**. The table below is an honest inventory.

| Area | Status | Notes |
|---|---|---|
| Attribute routing | ✅ Stable | All HTTP verbs, path params, prefix controllers |
| Parameter binding | ✅ Stable | Body, Param, Query, Headers, Request, UploadedFile, UploadedFiles |
| Middleware pipeline | ✅ Stable | Global + per-controller + per-route, `$next` chaining |
| Built-in middleware | ✅ Stable | CORS, BodyParser, Compression, SecurityHeaders, RequestId |
| Rate limiting | ✅ Stable | Sliding-window counter, pluggable `RateLimiterStore`, `#[Throttle]` attribute, `RateLimiter` service |
| Dependency injection | ✅ Stable | PSR-11, Symfony DI engine, reflection fallback, circular detection |
| Typed config (`#[Env]`) | ✅ Stable | Constructor and property-based wiring |
| Exception hierarchy | ✅ Stable | 22 HTTP exception classes + pluggable handler |
| DTO validation | ✅ Stable | Symfony Validator, auto-populated from request body |
| Response metadata | ✅ Stable | `#[HttpCode]`, `#[Header]` on class or method |
| Eloquent ORM | ✅ Stable | Illuminate Database v13, all connections, read replicas |
| Server-Sent Events | ✅ Stable | WHATWG-compliant framing, `StreamedEvent`, generator-based |
| CLI (`bin/bingo`) | ✅ Stable | 10 built-in commands, user command registration via DI |
| Code generators | ✅ Stable | controller, service, middleware, model, migration, command, exception |
| Database migrations | ✅ Stable | timestamp-ordered, `db:migrate` command |
| Application exception handler | ✅ Stable | Pluggable via `$app->exceptionHandler()` or container binding |
| Unit tests | ✅ 200/200 | 377 assertions, runtime ~90ms |
| Integration / feature tests | ❌ Missing | Full request-cycle tests not yet written |
| Migration history tracking | ❌ Missing | Re-runs all files; no state table |
| Controller auto-discovery | ❌ By design | Explicit registration in `bootstrap/app.php` |
| OpenAPI/Swagger generation | ❌ Roadmap | |
| WebSocket support | ❌ Roadmap | |
| Queue system | ❌ Out of scope | Use a dedicated queue service |

### Test results (current HEAD)

```
OK (200 tests, 377 assertions) — ~90ms
```

All tests are unit tests. The framework has no runtime test failures.

---

## Architecture Overview

```
public/index.php
  └─ bootstrap/app.php          ← register controllers, DI bindings, exception handler
       └─ Application::run()
            ├─ Container::compile()
            ├─ Request::createFromGlobals()
            ├─ MiddlewarePipeline::process()
            │    Global middleware stack (CORS → BodyParser → … → RateLimit)
            │    └─ Router::dispatch()
            │         Reflection discovers route + resolves #[Body], #[Param], …
            │         Per-route middleware pipeline ($next)
            │         Controller method called → Response
            │         RouteResponseMetadata::apply()  ← #[HttpCode], #[Header]
            └─ Response::send()
```

**Namespaces**

| Namespace | Location | Purpose |
|---|---|---|
| `Bingo\` | `core/Bingo/` | Framework code — do not modify |
| `App\` | `app/` | Your application |
| `Config\` | `config/` | Typed config classes (yours to edit) |

**Key contracts**

| Interface | Purpose |
|---|---|
| `Bingo\Contracts\MiddlewareInterface` | Every middleware must implement this |
| `Bingo\Contracts\ExceptionHandlerInterface` | Pluggable JSON error format |
| `Bingo\Config\Driver\DatabaseDriver` | Driver config returned by `DbConfig::$connections` |

---

## Setting Up Locally

```bash
git clone https://github.com/sadhakbj/bingo.git
cd bingo
composer install
cp .env.example .env
php bin/bingo serve
```

Run the test suite:

```bash
composer test
```

Run a single test class:

```bash
vendor/bin/phpunit --filter RouterTest
```

Create and run migrations:

```bash
php bin/bingo db:migrate
```

List all routes:

```bash
php bin/bingo show:routes
```

---

## Codebase Map

```
core/Bingo/
├── Application.php                  # Application kernel
├── Attributes/
│   ├── Config/Env.php               # #[Env('VAR', default: ...)]
│   ├── Middleware.php               # #[Middleware([...])]
│   └── Route/
│       ├── ApiController.php        # #[ApiController('/prefix')]
│       ├── Get.php … Options.php    # HTTP verb attributes
│       ├── Body.php … UploadedFiles # Parameter binding attributes
│       ├── HttpCode.php             # #[HttpCode(201)]
│       └── Header.php               # #[Header('Name', 'value')]
├── Config/
│   ├── ConfigLoader.php             # #[Env] → typed object
│   ├── DatabaseConfig.php           # Internal config container
│   └── Driver/
│       ├── DatabaseDriver.php       # Interface
│       ├── MySqlConfig.php
│       ├── PgSqlConfig.php
│       └── SQLiteConfig.php
├── Console/
│   ├── Kernel.php                   # CLI kernel + command registry
│   └── Command/
│       ├── ServeCommand.php         # php bin/bingo serve
│       ├── ShowRoutesCommand.php
│       ├── MigrateCommand.php       # php bin/bingo db:migrate
│       └── Generate*Command.php     # Code generators
├── Container/
│   └── Container.php                # PSR-11 + Symfony DI
├── Contracts/
│   ├── ExceptionHandlerInterface.php
│   └── MiddlewareInterface.php
├── Data/
│   ├── DataTransferObject.php       # Input DTO base
│   └── DTOCollection.php
├── Database/
│   └── Database.php                 # Eloquent Capsule singleton
├── DTOs/Http/
│   └── ApiResponse.php              # JSON response envelope
├── Exceptions/
│   ├── ExceptionHandler.php         # Default handler
│   └── Http/
│       ├── HttpException.php        # Base
│       └── *.php                    # 21 typed subclasses
├── Http/
│   ├── Request.php
│   ├── Response.php                 # ::json(), ::eventStream(), ::stream()
│   ├── StreamedResponse.php
│   ├── Router/
│   │   ├── Router.php
│   │   └── RouteResponseMetadata.php
│   ├── Middleware/
│   │   ├── MiddlewarePipeline.php
│   │   ├── CorsMiddleware.php
│   │   ├── BodyParserMiddleware.php
│   │   ├── CompressionMiddleware.php
│   │   ├── SecurityHeadersMiddleware.php
│   │   ├── RequestIdMiddleware.php
│   │   └── RateLimitMiddleware.php
│   └── Sse/
│       ├── SseEncoder.php           # WHATWG frame encoding
│       └── StreamedEvent.php
└── Validation/
    └── ValidationException.php
```

---

## Stability Matrix

### What you can rely on

These APIs are stable for v1.0 and will not break without a major version bump:

- All route attributes (`#[Get]`, `#[Post]`, `#[ApiController]`, etc.)
- All parameter binding attributes (`#[Body]`, `#[Param]`, `#[Query]`, `#[Headers]`, `#[Request]`, `#[UploadedFile]`, `#[UploadedFiles]`)
- Response metadata attributes (`#[HttpCode]`, `#[Header]`)
- `MiddlewareInterface::handle(Request, callable): Response`
- `ExceptionHandlerInterface::handle(Throwable): Response`
- `Application::create()`, `::controllers()`, `::singleton()`, `::bind()`, `::instance()`, `::exceptionHandler()`, `::use()`
- `Response::json()`, `::eventStream()`, `::stream()`
- `DataTransferObject` public API
- `ApiResponse` static factory methods
- All `Bingo\Exceptions\Http\*` exception classes
- `Bingo\Config\Driver\DatabaseDriver` interface
- All CLI commands and their argument/option signatures

### What may still evolve

- Internal router implementation details (not public API)
- Container internals below the `Container` public interface
- The exact format of the `data` field in debug error responses
- `RateLimitMiddleware` storage mechanism (currently in-process)

---

## Known Limitations

These are deliberate or accepted trade-offs, not bugs:

**Rate limiting requires Redis in production.** The framework uses `RedisStore` (phpredis) as the production backend and automatically falls back to `FileStore` in local development when phpredis is not loaded. There is no in-memory store — static arrays reset on every request under PHP's built-in server. Install phpredis via `pecl install redis` or `docker-php-ext-enable redis`.

**No migration history.** `db:migrate` runs every file in `database/migrations/` on every call. If you need idempotent migrations, guard each with `if (!Capsule::schema()->hasTable(...))` (the generated stub does this by default), or implement a state table yourself.

**No controller auto-discovery.** Controllers must be registered explicitly in `bootstrap/app.php`. This is intentional — it makes the list of active routes auditable without scanning the filesystem.

**Route parameter ordering.** Within a single controller, declare specific routes (e.g. `/search`, `/upload`) before wildcard routes (e.g. `/{id}`), or the wildcard will match first. This is a Symfony Routing constraint, not a Bingo bug.

**`EventSource` cannot send custom headers.** The SSE implementation uses `GET` (browser `EventSource` API constraint). Authentication over SSE must use cookies or a query parameter — not `Authorization` headers.

**`CompressionMiddleware` skips streaming responses.** `StreamedResponse` and `text/event-stream` are correctly excluded from gzip buffering.

---

## Roadmap

The items below are not committed to a release date. They represent known gaps that would make Bingo more complete.

### Near-term (v1.x)

- [ ] **Integration / feature tests** — Full HTTP request-cycle tests using Symfony's `HttpKernelBrowser` or an in-process test client
- [ ] **Migration history tracking** — A `migrations` table to track which files have already run, making `db:migrate` idempotent without `hasTable()` guards
- [ ] **`#[Inject]` parameter attribute** — Resolve a named container binding directly as a controller parameter, similar to NestJS `@Inject()`

### Medium-term (v2.x)

- [ ] **OpenAPI generation** — Derive an OpenAPI 3.x spec from route attributes, DTO constraint annotations, and `#[HttpCode]`/`#[Header]` metadata
- [ ] **Formal module system** — Group controllers, services, and config into self-contained modules that register themselves
- [ ] **Guards** — Dedicated authentication / authorization layer sitting between global middleware and per-route middleware

### Out of scope

- Full-stack features (views, Blade templates, Livewire equivalents)
- Built-in queue system (use RabbitMQ, AWS SQS, or a dedicated library)
- WebSocket server (use Ratchet or Swoole separately)

---

## Contribution Guidelines

### Before opening a PR

1. Open an issue first for non-trivial changes. Describe the problem and your proposed solution. This avoids duplicate effort and ensures the change aligns with project goals.
2. For bug fixes, include a failing test that passes after your fix.
3. For new features, include unit tests. The goal is to keep coverage at 100% for core logic.

### Branch naming

```
feature/short-description
fix/short-description
docs/short-description
```

### Commit messages

Use the conventional commit format:

```
feat: add #[Inject] parameter attribute for named DI bindings
fix: router swallows middleware exceptions when $next is inside catch
docs: document SSE reconnection behaviour
test: add integration tests for full request cycle
```

### What makes a good contribution

- A single, focused change. A PR that fixes a bug, adds a feature, and refactors three files will be asked to split.
- Tests. All new framework code requires unit tests. No tests → no merge.
- Strict types. Every PHP file must start with `declare(strict_types=1)`.
- No `core/Bingo/` modification for application concerns. If the change is about how someone uses the framework, it likely belongs in `app/` or `config/`.

---

## Code Standards

**PHP version:** `^8.4` — use modern features freely (constructor promotion, match, named arguments, attributes, readonly, fibers where relevant).

**Strict types:** `declare(strict_types=1)` in every file.

**Formatting:** Enforced via [Mago](https://mago.carthage.software). Run `mago format` before committing. A CI formatter check is planned but not yet wired.

The project's `mago.toml` tunes the `default` preset to keep code compact and aligned. The non-default formatter keys are:

| Key | Value | Effect |
|---|---|---|
| `align-assignment-like` | `true` | Column-aligns `=>` in multiline arrays and `=` in consecutive assignments / class properties / constants |
| `align-named-arguments` | `true` | Column-aligns `:` across named arguments in a call |
| `align-parameters` | `true` | Column-aligns the variable column in multiline parameter lists (especially promoted constructor properties) |
| `trailing-comma` | `true` | Re-adds the trailing comma the preset would otherwise strip from multiline lists |
| `preserve-breaking-*` (chains, args, arrays, params, attrs, conditionals) | `true` | If you broke a construct across lines on purpose, mago keeps it broken instead of collapsing back to one line |

**Known limitation.** `align-parameters` aligns the variable column but does **not** align `=` defaults in promoted constructor properties. If you want that, declare the properties in the class body instead — `align-assignment-like` covers regular properties fully.

If you disagree with one of these settings, open an issue before changing it — the choices are deliberate and apply repo-wide.

**Namespaces:**
- Framework code: `Bingo\` → `core/Bingo/`
- Application code: `App\` → `app/`
- Config: `Config\` → `config/`

**Naming:**
- Attributes: `PascalCase` (PHP attribute convention)
- Exceptions: end in `Exception`
- Middleware: end in `Middleware`
- Services: end in `Service`
- Commands: end in `Command`

**No magic.** If something is not obvious from the type signature or attribute, add a one-line docblock. Avoid inline comments that restate the code.

**`core/Bingo/` is the framework.** Application-layer classes (`App\*`) should never bleed into it. The framework does not import from `App\` or `Config\`.

---

## Testing

Tests live in `tests/Unit/` and are namespaced under `Tests\Unit\`.

```
tests/
├── Unit/
│   ├── Bingo/                # Framework core tests
│   │   ├── Application/
│   │   ├── Config/
│   │   ├── Container/
│   │   ├── Data/
│   │   ├── DTOs/Http/
│   │   ├── Exceptions/
│   │   ├── Http/
│   │   │   ├── Middleware/
│   │   │   └── Sse/
│   │   └── Router/
│   └── App/                  # Application-layer tests
│       ├── DTOs/
│       └── Middleware/
└── Stubs/                    # Test doubles
    ├── Controllers/
    └── Services/
```

Run the full suite:

```bash
composer test
```

Run a single test:

```bash
vendor/bin/phpunit --filter SseEncoderTest
```

Run with coverage (requires Xdebug or PCOV):

```bash
composer test:coverage
```

When writing tests for framework code, use the stubs in `tests/Stubs/` rather than real `app/` classes. This keeps framework tests isolated from application-layer changes.

---

## License

MIT — [Bijaya Prasad Kuikel](https://github.com/sadhakbj)
