# Bingo Framework — Development Roadmap

> **Author's note:** This is an opinionated, honest roadmap. Not a wishlist. Every phase is sequenced by what actually unlocks the next thing. Features are only added when they pull their weight.

---

## What Bingo Is

A PHP 8.5+ API-first microservice framework. Not Laravel (too heavy). Not Slim (too bare). Not a port of NestJS — PHP has its own idioms.

**The core bet:** PHP 8 attributes + automatic OpenAPI generation + small footprint = the framework PHP microservices have been missing.

**Target audience:** Developers building JSON APIs and microservices who want structure without Laravel's weight.

---

## Current State (as of initial build — ~2 hours in)

### ✅ Actually Done

- PHP 8 attribute-based routing (`#[Get]`, `#[Post]`, `#[ApiController]`, etc.)
- Parameter injection via attributes (`#[Body]`, `#[Query]`, `#[Param]`, `#[Headers]`, `#[UploadedFile]`)
- Global middleware pipeline (CORS, BodyParser, Compression, SecurityHeaders, RequestId, RateLimit)
- DTO system with Symfony Validator integration (`readonly`, `fromRequest()`, auto-422 on failure)
- `ApiResponse` envelope DTO
- `DTOCollection` (iterable, filterable)
- Laravel Eloquent ORM integration (SQLite)
- Symfony Console CLI (`php bin/framework show:routes`)
- Development vs Production app factory (`Application::development()`, `::production()`)
- `Request` / `Response` wrappers extending Symfony HttpFoundation

### ❌ Known Bugs (fix before building new things)

- `database/migrate.php` references wrong namespace: `Framework\Database\Database` → should be `Core\Database\Database`
- `UserService::emailExists()` and `persistUser()` are hardcoded stubs — not wired to the database
- `app/Http/Middleware/AuthMiddleware.php` and `LogMiddleware.php` return `true` and do nothing
- Route-level `#[Middleware]` runs `handle()` without `$next` — cannot modify responses
- `RateLimitMiddleware` uses PHP static memory — resets per process, effectively useless in FPM

### 🔶 Structural Weaknesses

- No Dependency Injection container — everything is manually `new`'d
- No test suite — `tests/` contains demo scripts, not PHPUnit tests
- No global exception handler — PHP's default error output bleeds through on uncaught exceptions
- Controllers must be manually registered in `bootstrap/app.php` — no auto-discovery

---

## Phase 0: Stabilize (Do This First)

> **Goal:** Make what exists actually work. No new features until the foundation is solid.

**Not negotiable. A framework with silent bugs and no tests is a liability.**

### 0.1 Fix Existing Bugs

- [ ] Fix `database/migrate.php` namespace (`Core\Database\Database`)
- [ ] Wire up `UserService::emailExists()` and `persistUser()` to actual Eloquent calls
- [ ] Delete or implement `AuthMiddleware` and `LogMiddleware` — stubs that return `true` are dangerous
- [ ] Fix route-level middleware: either wire it through `$next` pipeline or document that it's pre-response only

### 0.2 Testing Infrastructure

Install PHPUnit. Write tests for the parts that exist. A framework without tests can't be refactored safely.

```bash
composer require --dev phpunit/phpunit
```

**Tests to write (priority order):**
1. Router — attribute discovery, route matching, param injection
2. DTO — `fill()`, `validate()`, `fromRequest()`, `toArray()`
3. Middleware pipeline — correct ordering, `$next` chaining, short-circuit on early return
4. `ApiResponse` — all factory methods (`success`, `error`, `notFound`, etc.)
5. `Request` helpers — `all()`, `input()`, `only()`, `except()`

### 0.3 Global Exception Handler

Right now if a controller throws an uncaught exception, PHP dumps HTML. Every API response must be JSON.

```php
// core/Exceptions/ExceptionHandler.php
// Catches Throwable, returns ApiResponse::error() with stack trace in dev, clean message in prod
```

**Exception types to handle:**
- `ValidationException` → 422 with field errors (already partially done)
- `NotFoundException` → 404 JSON
- `UnauthorizedException` → 401 JSON
- `ForbiddenException` → 403 JSON
- `Throwable` (catch-all) → 500 JSON (with trace in dev, generic in prod)

---

## Phase 1: Dependency Injection Container

> **Goal:** Make the framework actually usable for real applications. Everything else depends on this.

**This is the load-bearing wall.** Without DI, you cannot: write testable services, swap implementations, manage lifecycles, or build a module system.

### Why not just use Symfony's container?

You can use `symfony/dependency-injection` to bootstrap fast, but wrap it behind your own interface. This way Bingo controls the API and can swap internals later.

```bash
composer require symfony/dependency-injection
```

### What to build

```php
// core/Container/Container.php  — wraps Symfony DIC
// core/Container/ContainerInterface.php  — your own interface

// Usage:
$container->bind(UserService::class);
$container->bind(MailerInterface::class, SmtpMailer::class);
$container->singleton(Database::class);
```

**Auto-wiring** based on constructor type hints — no manual binding for concrete classes.

### Controller resolution via container

Right now: `new UsersController()`
After DI: `$container->make(UsersController::class)` — dependencies injected automatically.

This also fixes the "register everything in bootstrap" problem — controllers can declare their dependencies and the container wires them.

### Providers (service registration)

```php
#[Provider]
class DatabaseServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Database::class, fn() => Database::setup());
    }
}
```

Auto-discovered from `app/Providers/` — no manual registration.

---

## Phase 2: OpenAPI Auto-Generation (The Killer Feature)

> **Goal:** Zero-config API documentation from existing attributes. This is Bingo's unique selling point.

**The insight:** You already have everything needed. `#[Get('/users/{id}')]` knows the path and method. `#[Body] CreateUserDTO` knows the request body schema (Symfony Validator constraints are on the properties). `#[Param('id')]` knows there's an integer path param. The framework just needs to read what's already there and emit OpenAPI 3.1 JSON.

**Inspiration:** .NET's Swagger integration — you wrote it, you know how good it feels when it just works.

```bash
composer require cebe/php-openapi
```

### What gets auto-generated

| Source | OpenAPI output |
|---|---|
| `#[ApiController('/users')]` | `tags: [users]`, path prefix |
| `#[Get('/users/{id}')]` | `GET /users/{id}` operation |
| `#[Param('id')] int $id` | `parameters: [{in: path, name: id, schema: {type: integer}}]` |
| `#[Query('page')] int $page = 1` | `parameters: [{in: query, name: page, required: false}]` |
| `#[Body] CreateUserDTO $dto` | `requestBody` with full JSON schema from DTO properties |
| Symfony `#[Assert\NotBlank]`, `#[Assert\Email]` | `required`, `format: email` in schema |
| `#[Assert\Range(min: 18)]` | `minimum: 18` in schema |
| `ApiResponse::success($data)` return | `responses: {200: ...}` |

### New attributes to add

```php
#[ApiSummary('Get user by ID')]         // operation summary
#[ApiDescription('Returns a single user')]  // operation description
#[ApiTag('Users')]                       // override auto-detected tag
#[ApiResponse(200, 'User found', UserDTO::class)]  // explicit response docs
#[Deprecated]                            // marks endpoint deprecated
```

### Built-in endpoints (zero config)

```
GET /openapi.json     → raw OpenAPI 3.1 spec
GET /docs             → Swagger UI (served from CDN, no build step)
GET /docs/redoc       → ReDoc UI alternative
```

Disable in production via `.env`:
```
OPENAPI_ENABLED=false
```

### DTO → JSON Schema mapping

`DataTransferObject::toJsonSchema()` — reads property types + Symfony constraints via reflection, outputs JSON Schema. This feeds directly into OpenAPI's `components/schemas`.

---

## Phase 3: Security Layer

> **Goal:** Make auth a first-class citizen, not an afterthought stub.

### 3.1 JWT Authentication (stateless — required for microservices)

```bash
composer require firebase/php-jwt
```

```php
// core/Auth/JwtGuard.php
// core/Attributes/Guard.php   — #[Guard(JwtGuard::class)]

#[Get('/users/me')]
#[Guard(JwtGuard::class)]
public function me(#[AuthUser] User $user): Response { ... }
```

`#[Guard]` runs before the controller, throws `UnauthorizedException` (→ 401) if not authenticated.
`#[AuthUser]` injects the authenticated user — resolved by the guard.

### 3.2 Guards (NestJS-style)

Guards run through the middleware pipeline properly (with `$next`). They can:
- Short-circuit and return a response
- Attach data to the request (e.g., authenticated user)
- Pass through to the controller

```php
interface GuardInterface
{
    public function canActivate(Request $request): bool;
}
```

### 3.3 API Key Auth (service-to-service)

```php
#[Guard(ApiKeyGuard::class)]
```

Reads `X-API-Key` header, validates against configured keys. Useful for internal service calls.

### 3.4 Role-Based Access Control

```php
#[Guard(JwtGuard::class)]
#[Roles('admin', 'moderator')]
public function deleteUser(#[Param('id')] int $id): Response { ... }
```

---

## Phase 4: Developer Experience

> **Goal:** Make building with Bingo fast and obvious.

### 4.1 Code Generation CLI

```bash
php bin/bingo make:controller UserController
php bin/bingo make:service UserService
php bin/bingo make:dto CreateUserDTO
php bin/bingo make:model User --migration
php bin/bingo make:resource User  # generates controller + service + DTO + model together
```

Generates boilerplate with the right attributes, namespace, and file location. Eliminates copy-paste.

### 4.2 Typed Configuration System

Replace raw `$_ENV` access with typed config objects.

```php
// config/app.php
return new AppConfig(
    env: AppEnv::from($_ENV['APP_ENV']),
    debug: (bool) $_ENV['APP_DEBUG'],
    corsOrigins: explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? ''),
);

// Usage anywhere via DI:
public function __construct(private AppConfig $config) {}
```

No more `getenv('APP_DEBUG')` scattered across the codebase.

### 4.3 Improve Route-Level Middleware

Wire `#[Middleware]` through the `$next` pipeline so middleware can modify the response. Right now it can only short-circuit.

### 4.4 Auto-Discovery of Controllers

Scan `app/Http/Controllers/` automatically. Remove manual registration from `bootstrap/app.php`.

```php
// bootstrap/app.php — should become:
$app = Application::development();
// that's it. controllers are auto-discovered.
```

### 4.5 Response Macros / Resource Classes

For complex output transformation:

```php
class UserResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_adult' => $this->age >= 18,
        ];
    }
}

// In controller:
return UserResource::collection($users)->toResponse();
```

---

## Phase 5: Observability & Production Readiness

> **Goal:** Make Bingo deployable with confidence.

### 5.1 Structured Logging

```bash
composer require monolog/monolog
```

- JSON log format (plays well with Datadog, CloudWatch, Loki)
- Automatic request context: method, path, status, duration, `X-Request-ID`
- Log levels: DEBUG, INFO, WARNING, ERROR
- Log channels: `app`, `http`, `database`, `security`

```php
// injected via DI:
public function __construct(private LoggerInterface $logger) {}

$this->logger->info('User created', ['user_id' => $user->id]);
```

### 5.2 Health Check Endpoints (Built-in)

```
GET /health          → overall status
GET /health/live     → liveness (is the process running?)
GET /health/ready    → readiness (are dependencies up?)
```

Auto-registered by the framework. No controller needed.

```php
// Extensible with custom indicators:
class DatabaseHealthIndicator implements HealthIndicator
{
    public function check(): HealthStatus { ... }
}
```

### 5.3 Real Rate Limiting

Replace in-memory rate limiter with APCu (single server) or Redis (distributed).

```bash
composer require symfony/rate-limiter
```

Pluggable backend:
```
RATE_LIMIT_STORE=apcu    # default
RATE_LIMIT_STORE=redis
RATE_LIMIT_STORE=memory  # dev only
```

### 5.4 Metrics (Optional but valuable)

Prometheus-compatible `/metrics` endpoint. Track:
- Request count by route and status code
- Response time histograms
- Active connections
- PHP memory and CPU

---

## Phase 6: Async & Events (When You Need It)

> **Add this when building something that actually needs it. Don't build it speculatively.**

### 6.1 Domain Events

```php
// Dispatch in service:
$this->events->dispatch(new UserCreatedEvent($user));

// Handle anywhere:
#[EventHandler]
public function onUserCreated(UserCreatedEvent $event): void
{
    $this->mailer->sendWelcomeEmail($event->user);
}
```

Synchronous by default. Async when configured with a queue backend.

### 6.2 Queue / Background Jobs

```bash
composer require symfony/messenger
```

Backends: Redis, RabbitMQ, database.

```php
$this->queue->dispatch(new SendWelcomeEmailJob($user));

// Worker:
php bin/bingo queue:work
```

### 6.3 Scheduled Tasks

```php
#[Schedule('0 9 * * *')]
public function sendDailyDigest(): void { ... }

// Run:
php bin/bingo schedule:run  # call from cron every minute
```

---

## What We're NOT Building (and Why)

| Feature | Why not |
|---|---|
| WebSockets | PHP-FPM is a bad runtime for persistent connections. Use Swoole/ReactPHP as a separate service. |
| Service Mesh (Envoy, Consul) | Infrastructure concern. Not a framework responsibility. |
| Helm Charts / Kubernetes manifests | Out of scope. Provide Docker guides in docs instead. |
| XML / MessagePack response formats | JSON is the standard for APIs in 2024+. Add if there's real demand. |
| Event Sourcing / Saga Pattern | Premature. Add if a real use case appears. |
| Built-in OAuth2 server | Use a dedicated auth service (Keycloak, Auth0, etc.) |

---

## Architecture Principles (Non-Negotiable)

1. **PHP 8.5+ only** — no compatibility hacks for older versions
2. **Attributes over configuration** — no YAML, no XML, no config arrays for routing/validation
3. **Small footprint** — every dependency must justify its presence. Vendor size matters for microservices.
4. **Fail fast, fail loud** — validation errors are 422, not silent. Missing config throws at boot.
5. **JSON everywhere** — all errors, all responses, all docs are JSON-first
6. **Testable by design** — DI container means every class can be tested in isolation
7. **Convention over configuration** — sensible defaults, override when needed

---

## Code Standards

- **PHPStan level 8** — strict static analysis
- **PSR-12** code style
- **`readonly`** for all DTOs and value objects
- **`strict_types=1`** in every file
- **No `mixed` types** — be explicit

---

## Success Metrics

| Metric | Target |
|---|---|
| Middleware overhead per request | < 5ms |
| Vendor size (no dev deps) | < 15MB |
| Time from `composer create-project` to first endpoint | < 5 minutes |
| OpenAPI spec generation time | < 50ms |
| PHPStan level | 8 (max) |
| Test coverage of `core/` | > 90% |

---

## Immediate Next Steps (What to Actually Do Now)

1. **Fix the migrate.php namespace bug** — 5 minutes
2. **Install PHPUnit, write 10 tests for the router** — validates the core works
3. **Build the global exception handler** — makes the framework usable
4. **Start the DI container** — everything else unlocks after this
5. **Then: OpenAPI generation** — this is what makes people choose Bingo

The DI container and OpenAPI generator are the two features that take this from "cool experiment" to "framework worth using."
