# Dependency injection — implementation reference

**Status: implemented.** Bingo uses `Core\Container\Container` backed by **Symfony `ContainerBuilder`**, plus a **reflection autowiring** path for classes you never register.

This document is a **reference** for how it works and how to extend it. For day-to-day usage, see **README.md** (Dependency Injection section) and **CLAUDE.md**.

---

## Why Symfony DI under the hood

The project already depended on Symfony HTTP, Routing, Validator, and Console. Adding `symfony/dependency-injection` completes the stack without exposing Symfony’s XML/YAML config to app authors — only Bingo’s small API surface (`singleton`, `bind`, `instance`, `make`, `compile`).

---

## Public API (`Core\Container\Container`)

| Method | Purpose |
|--------|---------|
| `singleton(string $abstract, ?string $concrete = null)` | Shared instance for the lifecycle; Symfony definition with `setShared(true)` + autowire |
| `bind(string $abstract, ?string $concrete = null)` | New instance per resolution; `setShared(false)` |
| `instance(string $abstract, object $instance)` | Pre-built object; bypasses Symfony for that id |
| `get(string $id)` / `make(string $id)` | PSR-11 resolution + Laravel-style alias |
| `has(string $id)` | PSR-11 |
| `compile()` | Idempotent; freezes Symfony definitions — call before HTTP `run()` after all registrations |

**Resolution order in `get()`:**

1. **`instance()`** map — always wins  
2. **Registered** `singleton` / `bind` ids — Symfony compiled container  
3. **Reflection fallback** — any concrete, non-abstract class with resolvable constructor dependencies  

The fallback recursively calls `get()` on typed parameters, respects registered singletons, detects circular dependencies, and honors optional/nullable parameters.

**Application wiring:** `Application` constructs `Container`, passes it to `Router` and `MiddlewarePipeline`, registers `AppConfig` and `DatabaseConfig` via `instance()`, and calls `container->compile()` at the start of `run()`.

---

## When to register explicitly

| Situation | Action |
|-----------|--------|
| Concrete class, constructor type-hints only other concretes / known services | Usually **nothing** — reflection resolves it |
| Interface → implementation | `$app->singleton(Interface::class, Concrete::class)` or `bind()` |
| Pre-wired config or third-party object | `$app->instance(SomeConfig::class, $object)` |

Register **before** `Application::run()` (or before first resolution that triggers `compile()` for Symfony-registered ids).

---

## Files (as built)

- `core/Container/Container.php` — main implementation  
- `core/Container/ContainerException.php`, `NotFoundException.php` — errors  
- `core/Attributes/Injectable.php`, `Singleton.php` — optional markers for future auto-scanning (not required for basic use)  
- `tests/Unit/Core/Container/ContainerTest.php` — regression coverage  

---

## Future extensions (not required for current apps)

- Dump compiled container to PHP for production cold start (Symfony supports this).  
- Request-scoped services (per-request instances).  
- Module / provider style bulk registration.  
- Tagged services for plugin-style discovery.  

---

*The original version of this file was a pre-implementation plan (“everything is `new`”). That plan is complete; treat the sections above as the source of truth.*
