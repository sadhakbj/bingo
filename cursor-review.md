# Bingo — Expert architectural review (beta)

**Context:** API-first / microservices-oriented PHP framework, NestJS-inspired (attributes, modules-by-convention in folder layout), pragmatic Laravel borrowings (Eloquent, `app/` tree, `Response::json` ergonomics). Heavy use of Symfony components (HTTP Foundation, Routing, Validator, Console, DependencyInjection).  
**Review date:** 2026-03-30  
**Codebase snapshot:** Day-4 trajectory; README and `composer.json` describe a more mature picture than some older internal notes.

---

## Verdict (short)

You are **not** doing something wrong by combining Symfony primitives with Eloquent and attribute routing. That is a coherent product shape: **Symfony as the engine, Nest-style DX on top, Laravel where the PHP ecosystem is strongest (ORM)**. The repo already shows several decisions that match what serious API frameworks need: a real middleware pipeline, a container with autowiring, typed config, PHPUnit coverage, and a single HTTP exception path.

The main work ahead is **consistency** (one JSON envelope and error contract everywhere), **edge-case correctness** (routing and `isApiPath`), and **honest limits** for production (rate limiting, multi-process behavior). None of that invalidates the direction.

---

## What is already strong

### 1. Clear positioning and documentation

The **README** is unusually good for a young framework: lifecycle diagram, parameter binding table, middleware semantics (including the “do not wrap `$next` in catch” note), DI registration patterns, and CLI usage. That reduces adoption friction and signals intent—many OSS PHP projects never reach this level of narrative.

### 2. Symfony as foundation (this is a feature, not “cheating”)

Using **HttpFoundation**, **Routing**, **Validator**, and **Console** is standard practice. Wrapping them behind `Core\Http\Request`, `Router`, and `DataTransferObject` is exactly how you avoid rebuilding low-value infrastructure. **Symfony DependencyInjection** behind your `Core\Container\Container` is a solid choice: you get compilation, autowiring for registered services, and room to grow without writing a second-rate container from scratch.

### 3. NestJS-like developer experience where it matters

- **Attribute routing** on methods with verb shortcuts (`#[Get]`, `#[Post]`, …) matches how Nest feels in TypeScript.
- **Parameter attributes** (`#[Body]`, `#[Query]`, `#[Param]`, `#[Headers]`, uploads, `#[Request]`) are the right abstraction for APIs.
- **`#[ApiController]`** enforcing a `Response` return type is a simple, enforceable boundary for JSON APIs.

### 4. Middleware done the modern way

`MiddlewarePipeline` implements a proper **`$next`-style chain** for global middleware. Route-level middleware is also composed through a nested pipeline (`Router::dispatch`), which fixes the older “middleware can’t see the response” class of problems. That aligns with how Express/Nest middleware is supposed to behave.

### 5. Configuration without magic arrays

`ConfigLoader` + `#[Env]` on constructors or properties is **clean, testable, and IDE-friendly**. The split between `DbConfig` (connection map) and per-driver classes is a scalable pattern for multi-tenant or multi-DB services later.

### 6. Exception handling centralized

`Application::handle()` wraps the pipeline in try/catch and delegates to `ExceptionHandler`, mapping `HttpException` and `ValidationException` to JSON. That is the correct **single choke point** for API error shape (even if not every branch uses the same envelope yet—see below).

### 7. Real tests

`composer test` runs **179 tests** with broad coverage of container, router, middleware, DTOs, config, and exceptions. For a framework at this stage, that is a major credibility signal and safety net.

### 8. Application code quality in the demo path

`UserService` uses Eloquent properly (`exists()`, `create()`, `NotFoundException` / `ConflictException`). Controllers stay thin. This is the right layering story for an API framework sample.

---

## Where “Laravel flavor” shows up (and whether it matters)

| Area | Observation | Take |
|------|----------------|------|
| **Eloquent** | Full Illuminate Database stack | Reasonable default for JSON APIs in PHP; alternative would be Doctrine or raw PDO—higher cost for little gain early on. |
| **Folder layout** | `app/Http`, `app/Services`, `app/Models` | Familiar to Laravel devs; also close to Nest’s module boundaries if you later introduce formal modules. |
| **`Request::all()`** merging query + body + JSON | Laravel-like ergonomics | Fine for DX; document precedence and security implications for mass-assignment-style patterns. |
| **Bootstrap `require` style** | Similar mental model to Laravel’s `bootstrap/app.php` | Normal for PHP apps. |

**Bottom line:** The mix is **coherent** if you name it: “Symfony core + Nest-style routing/DI + Laravel’s ORM story.” That is a legitimate niche.

---

## Issues to fix or tighten (prioritized)

### P1 — Response envelope inconsistency

Today, some paths return **`ApiResponse`-shaped JSON** (via `ExceptionHandler`), while the router still returns **ad-hoc payloads** for several API cases, for example:

- 404 / 405 on API routes: `{ "error": "..." }` instead of `ApiResponse::notFound()` / a dedicated factory.
- Validation failures inside `Router` parameter resolution: `{ "errors": ... }` rather than the same structure as `ApiResponse::validation()` (field map vs envelope).
- `RateLimitMiddleware` uses another ad-hoc `{ error, message }` shape.

**Recommendation:** Route *all* framework-generated JSON through small helpers (e.g. `Response::apiError(...)`, `Response::apiValidation(...)`) that always serialize `ApiResponse` (or a single interface). Clients should see one contract.

### P1 — `Router::isApiPath()` and empty `#[ApiController]` prefixes

`isApiPath()` treats an **empty prefix** as “matches everything” because of `if ($prefix === '' || str_starts_with(...))`. Any controller registered as `#[ApiController]` **without** a path segment produces an empty prefix (`null` → `''` after `rtrim`), which can force **JSON 404/405 for every unknown URL**, including non-API `HomeController` routes.

**Recommendation:** Skip empty prefixes when recording API prefixes, or treat “root API” as `'/'` explicitly and compare with stricter rules. Document that bare `#[ApiController]` is invalid unless that behavior is intentional.

### P2 — Error leakage in `Router` catch-all

The broad `catch (\Throwable)` branch can return **exception messages in JSON** for API controllers. That bypasses `ExceptionHandler` and can expose internals in production if triggered.

**Recommendation:** Re-throw or delegate to `ExceptionHandler` so debug vs production behavior stays centralized.

### P2 — Rate limiting and multi-process deployments

`RateLimitMiddleware` uses **static in-memory storage**. Under PHP-FPM or multiple workers, limits are **per process**, not global—fine for demos, misleading for “production middleware” claims.

**Recommendation:** Document as explicitly in-memory / dev-oriented until backed by Redis, APCu, or an external gateway (API gateway, service mesh, nginx).

### P3 — Route registration order footgun

Symfony `UrlMatcher` order + greedy `/{id}` remains a **classic trap** (e.g. `GET /users/search` vs `/{id}`). You already document this for `/search`; the same applies to any new static segment added after `/{id}`.

**Recommendation:** Long-term, consider **explicit route priority** or a **path-then-placeholder** sorting pass during registration. Short-term, keep documenting and add a `show:routes` warning in dev when static paths are registered after `{param}` routes.

### P3 — PHP version drift

`composer.json` requires **`php ^8.5`**; local CI/agent run showed **PHPUnit on PHP 8.4**. Ensure Dockerfile / CI / README agree on the minimum version so contributors are not surprised.

### P3 — Stale internal documentation

`CLAUDE.md`, `docs/framework-development-plan.md`, and `docs/di-container-plan.md` were **refreshed** to match the current codebase (DI, migrations via `php bin/bingo db:migrate`, route middleware pipeline, real services). If you add features, update those files alongside README so they do not drift again.

**Recommendation:** Archive or refresh those docs so they do not contradict the README—stale planning docs erode trust faster than missing docs.

---

## Architectural opportunities (post-beta, not blockers)

1. **Formal “modules” or packages** — Nest’s strength is bounded contexts. Your folder structure is ready; you may later add optional module classes that register routes/services (still explicit, not magic auto-discovery if you want predictability).

2. **PSR-7 / PSR-15** — Optional bridges would help interoperability; not required if you commit to Symfony Request/Response as *the* contract.

3. **OpenAPI** — Your roadmap already points here; attributes + reflection are a natural source for generation.

4. **Param converter layer** — Router argument resolution is growing; extracting it to a dedicated class (or small strategy objects) will keep `Router` readable as you add enums, UUIDs, and custom casts.

5. **CLI vs HTTP container lifecycle** — Console bootstrap reuses `bootstrap/app.php` without `run()`; that is good. Keep an eye on **when** `compile()` runs relative to user registrations in larger apps.

---

## Summary table

| Dimension | Grade (beta-appropriate) | Note |
|-----------|---------------------------|------|
| Architecture | **Strong** | Clear layers; Symfony-backed core is sound. |
| DX / Nest alignment | **Strong** | Attributes and DI match stated goals. |
| API consistency | **Needs work** | Unify error/validation envelopes. |
| Production hardening | **Early** | Rate limit storage, error paths, observability. |
| Documentation | **Strong public / weak internal** | README excellent; sync `docs/` with reality. |
| Test discipline | **Strong** | PHPUnit coverage is a major asset. |

---

## Closing

For **day four of a beta**, this is an impressive skeleton: it already behaves like a small framework rather than a tutorial router. The “Laravel smell” is mostly **Eloquent and familiar folders**—that is a strategic choice, not an accident to be ashamed of. The highest-leverage next steps are **one JSON contract everywhere**, **tightening router edge cases**, and **aligning secondary docs with the code** so newcomers (and future you) are not misled.

This file was generated as a one-off architectural review (`cursor-review.md`); fold any lasting decisions into README or `docs/` as you prefer.
