# Introduction

Bingo is a PHP 8.5+ framework for building API-first applications.

It is designed for projects that need modern developer ergonomics — attribute-based routing, typed configuration, automatic discovery, and built-in validation — without pulling in a full application framework.

---

## Design Goals

- **Explicit over magic.** Controllers, routes, and configuration use PHP attributes so the shape is visible in the source.
- **Minimal setup.** Create a controller, annotate it, return a response. The framework discovers and registers everything else.
- **Predictable request flow.** A fixed middleware pipeline handles every request; per-route middleware nests inside it.
- **Type-safe configuration.** Environment variables are mapped into readonly PHP objects instead of string arrays.

---

## Framework Stack

| Layer | Technology |
|---|---|
| HTTP foundation | Symfony HttpFoundation |
| Routing | Symfony Routing (`RouteCollection` + `UrlMatcher`) |
| Validation | Symfony Validator |
| DI container | Symfony DependencyInjection + reflection-based autowiring |
| Console | Symfony Console |
| ORM | Illuminate Database / Eloquent |
| Logging | Monolog v3 (PSR-3) |
| Environment | vlucas/phpdotenv |

---

## Feature Highlights

- **Attribute routing** — `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]`, `#[Head]`, `#[Options]`, `#[Route]`
- **Automatic controller and command discovery** from `app/` with a filemtime-validated cache
- **Typed configuration** via `#[Env]` attributes on readonly classes
- **DTO-based request validation** — extend `DataTransferObject`, annotate with Symfony constraints, and the framework validates before your action is called
- **`ValidatedRequest`** — form-style request objects as an alternative to DTOs
- **Per-route and global middleware** — class-level and method-level `#[Middleware]`
- **Sliding-window rate limiting** — Redis-backed with file-store fallback for dev
- **Server-Sent Events** — `Response::eventStream()` with automatic end-of-stream signalling
- **Raw chunked streaming** — `Response::stream()` for non-SSE use cases
- **Structured logging** — Monolog with slog-style text or JSON format
- **Consistent JSON exception handling** — every unhandled throwable becomes a typed JSON response
- **Eloquent ORM** — models, migrations, and read-replica support

---

## Request Lifecycle

```
public/index.php
  → bootstrap/app.php          (DI bindings, exception handler, discovery)
  → Application::run()
      → container->compile()   (Symfony DI freeze)
      → bootDiscovery()        (load controller/command cache)
      → Request::createFromGlobals()
      → MiddlewarePipeline::process()
            CorsMiddleware
            BodyParserMiddleware
            CompressionMiddleware
            SecurityHeadersMiddleware
            RequestIdMiddleware
            RateLimitMiddleware   (production only)
            → Router::dispatch()
                  route #[Middleware] pipeline
                  parameter binding (Body, Param, Query, …)
                  controller action
      → Response::send()
```

Uncaught throwables flow through `ExceptionHandlerInterface` and are rendered as JSON before the response is sent.

---

## Recommended Reading Order

If you are new to Bingo:

1. [Getting Started](getting-started.md)
2. [Routing](routing.md)
3. [Parameter Binding](parameter-binding.md)
4. [DTOs and Validation](dtos-and-validation.md)
5. [Responses](responses.md)
6. [Dependency Injection](dependency-injection.md)
