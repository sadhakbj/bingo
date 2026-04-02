# Introduction

Bingo is a PHP 8.5+ framework for API-first development.

It is designed for projects that want modern developer ergonomics without relying on manual route registration, configuration arrays, or repetitive wiring.

## Design goals

- Keep application code explicit and readable.
- Reduce the amount of setup required to define controllers, routes, and configuration.
- Make request handling predictable through a clear middleware and routing pipeline.
- Use PHP attributes and typed objects where they improve clarity.

## Highlights

- Attribute-based routing
- Automatic controller discovery
- Typed configuration objects
- Dependency injection with container resolution
- DTO-based validation
- Middleware support at the global, controller, and route level
- Built-in rate limiting
- Structured logging
- Consistent JSON exception handling
- Eloquent ORM integration
- CLI commands for discovery, generation, and database operations

## Framework stack

Bingo is built on top of Symfony components, including HTTP Foundation, Routing, Validator, Console, and Dependency Injection.

Eloquent ORM is available for application data access.

## Typical workflow

1. Create a controller in `app/Http/Controllers`.
2. Add route attributes such as `#[Get]` or `#[Post]`.
3. Bind request data with `#[Body]`, `#[Param]`, or `#[Query]`.
4. Return a `Bingo\Http\Response` instance.
5. Let the framework handle discovery, validation, middleware, and exception rendering.

## Recommended reading order

If you are new to Bingo, start with:

1. [Getting Started](getting-started.md)
2. [Routing](routing.md)
3. [Parameter Binding](parameter-binding.md)
4. [DTOs and Validation](dtos-and-validation.md)
5. [Dependency Injection](dependency-injection.md)
