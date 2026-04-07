# Bingo

Bingo is a PHP 8.4+ framework for building API-first applications.

It combines attribute-based routing, automatic controller discovery, typed configuration, dependency injection, request validation, Eloquent ORM, rate limiting, structured logging, and SSE streaming — all built on top of Symfony components.

---

## What Makes Bingo Different

- **No route registration.** Drop a controller in `app/Http/Controllers`, add a `#[Get]` attribute, and the route is live. The discovery system handles everything.
- **Typed configuration.** Environment variables are mapped into readonly PHP objects instead of string arrays. Inject `AppConfig` anywhere — no config façade needed.
- **Attribute-first.** Routes, middleware, parameter binding, throttling, and response metadata are all expressed as PHP attributes on the controller.
- **Production ready.** Redis-backed sliding-window rate limiting, structured JSON logging, compressed responses, security headers, and request IDs work out of the box.

---

## Quick Start

```bash
git clone https://github.com/sadhakbj/bingo.git
cd bingo
composer install
cp .env.example .env
php bin/bingo serve
```

Create your first controller in `app/Http/Controllers/HelloController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Bingo\Attributes\Route\ApiController;
use Bingo\Attributes\Route\Get;
use Bingo\Http\Response;

#[ApiController('/api')]
class HelloController
{
    #[Get('/hello')]
    public function hello(): Response
    {
        return Response::json(['message' => 'Hello from Bingo!']);
    }
}
```

```bash
curl http://127.0.0.1:8000/api/hello
# {"message":"Hello from Bingo!"}
```

The route is discovered automatically — no registration needed.

For local development, copying `.env.example` to `.env` is the easiest path. Bingo
can also read variables from the shell environment directly, and in containers or
Kubernetes a physical `.env` file is optional because the platform can inject env vars.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.4+ |
| Composer | 2.x |
| Database | SQLite 3, MySQL 8+, or PostgreSQL 14+ |

Optional: `ext-redis` (phpredis) for distributed rate limiting in production.

---

## Documentation

### Prologue
- [Introduction](docs/introduction.md) — what Bingo is and why it exists
- [Getting Started](docs/getting-started.md) — install, configure, and run your first request

### The Basics
- [Routing](docs/routing.md) — HTTP verb attributes, prefixes, response metadata
- [Middleware](docs/middleware.md) — global, controller, and per-route middleware
- [Parameter Binding](docs/parameter-binding.md) — body, route params, query, headers, file uploads
- [Responses](docs/responses.md) — `Response`, `ApiResponse`, `StreamedResponse`
- [DTOs and Validation](docs/dtos-and-validation.md) — input DTOs, `ValidatedRequest`, `DTOCollection`
- [Exception Handling](docs/exception-handling.md) — built-in HTTP exceptions and custom handlers

### Going Deeper
- [Configuration](docs/configuration.md) — typed config classes and `#[Env]` attributes
- [Dependency Injection](docs/dependency-injection.md) — auto-resolution, bindings, service providers
- [Auto-Discovery](docs/auto-discovery.md) — how controllers and commands are found at runtime
- [Eloquent ORM](docs/eloquent-orm.md) — models, relationships, migrations, read replicas
- [Rate Limiting](docs/rate-limiting.md) — sliding-window throttling with Redis or file store
- [Logging](docs/logging.md) — structured Monolog logger, formats, and injection
- [Server-Sent Events](docs/server-sent-events.md) — SSE streaming and raw chunked responses

### CLI & Tooling
- [CLI](docs/cli.md) — all `bin/bingo` commands and code generators
- [Testing](docs/testing.md) — PHPUnit setup and test layout

### Production
- [Deployment](docs/deployment.md) — Docker, Kubernetes, and pre-flight checklist
- [Project Structure](docs/project-structure.md) — directory layout and conventions
- [Helpers](docs/helpers.md) — global helper functions

---

## Example: Full CRUD Controller

```php
#[ApiController('/users')]
class UsersController
{
    public function __construct(private readonly UserService $service) {}

    #[Get('/')]
    public function index(
        #[Query('page')]  int $page  = 1,
        #[Query('limit')] int $limit = 20,
    ): Response {
        return Response::json(ApiResponse::success($this->service->paginate($page, $limit))->toArray());
    }

    #[Get('/{id}')]
    public function show(#[Param('id')] int $id): Response
    {
        return Response::json(ApiResponse::success($this->service->find($id))->toArray());
    }

    #[Post('/')]
    #[HttpCode(201)]
    public function create(#[Body] CreateUserDTO $dto): Response
    {
        return Response::json(ApiResponse::success($this->service->create($dto), statusCode: 201)->toArray(), 201);
    }

    #[Put('/{id}')]
    public function update(#[Param('id')] int $id, #[Body] UpdateUserDTO $dto): Response
    {
        return Response::json(ApiResponse::success($this->service->update($id, $dto))->toArray());
    }

    #[Delete('/{id}')]
    public function destroy(#[Param('id')] int $id): Response
    {
        $this->service->delete($id);
        return Response::json(null, 204);
    }
}
```
