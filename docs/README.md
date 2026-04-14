# Bingo Documentation

Bingo is a PHP 8.4+ framework for API-first applications built on Symfony components and Eloquent ORM.

---

## Prologue

- [Introduction](introduction.md) — what Bingo is and why it exists
- [Getting Started](getting-started.md) — install, configure, and run your first request

## The Basics

- [Routing](routing.md) — HTTP verb attributes, prefixes, response metadata
- [Middleware](middleware.md) — global, controller, and per-route middleware
- [Parameter Binding](parameter-binding.md) — body, route params, query, headers, file uploads
- [Responses](responses.md) — `Response`, `ApiResponse`, `StreamedResponse`
- [DTOs and Validation](dtos-and-validation.md) — input DTOs, `ValidatedRequest`, `DTOCollection`
- [Exception Handling](exception-handling.md) — built-in HTTP exceptions and custom handlers

## Going Deeper

- [Configuration](configuration.md) — typed config classes and `#[Env]` attributes
- [Dependency Injection](dependency-injection.md) — auto-resolution, bindings, service providers
- [Auto-Discovery](auto-discovery.md) — how controllers and commands are found at runtime
- [Eloquent ORM](eloquent-orm.md) — models, relationships, migrations, read replicas
- [Doctrine ORM Plan](doctrine-orm-plan.md) — proposed architecture and phased migration strategy
- [Rate Limiting](rate-limiting.md) — sliding-window throttling with Redis or file store
- [Logging](logging.md) — structured Monolog logger, formats, and injection
- [Server-Sent Events](server-sent-events.md) — SSE streaming and raw chunked responses

## CLI & Tooling

- [CLI](cli.md) — all `bin/bingo` commands and code generators
- [Testing](testing.md) — PHPUnit setup and test layout

## Production

- [Deployment](deployment.md) — Docker, Kubernetes, and pre-flight checklist
- [Project Structure](project-structure.md) — directory layout and conventions
- [Helpers](helpers.md) — global helper functionstion](dependency-injection.md)
- [Rate Limiting](rate-limiting.md)
- [Server-Sent Events](server-sent-events.md)
- [Logging](logging.md)
- [Exception Handling](exception-handling.md)

## Runtime and tooling

- [Eloquent ORM](eloquent-orm.md)
- [CLI](cli.md)
- [Testing](testing.md)
- [Deployment](deployment.md)
- [Project Structure](project-structure.md)
