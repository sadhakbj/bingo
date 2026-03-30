# Bingo — Expert architectural review (beta)

**Context:** API-first / microservices-oriented PHP framework, NestJS-inspired (attributes, modules-by-convention in folder layout), pragmatic Laravel borrowings (Eloquent, `app/` tree, `Response::json` ergonomics). Heavy use of Symfony components (HTTP Foundation, Routing, Validator, Console, DependencyInjection).  
**Review date:** 2026-03-30  
**Codebase snapshot:** Day-4 trajectory; README and `composer.json` describe a more mature picture than some older internal notes.

---

## Verdict (short)

You are **not** doing something wrong by combining Symfony primitives with Eloquent and attribute routing. That is a coherent product shape: **Symfony as the engine, Nest-style DX on top, Laravel where the PHP ecosystem is strongest (ORM)**. The repo already shows several decisions that match what serious API frameworks need: a real middleware pipeline, a container with autowiring, typed config, PHPUnit coverage, and a single HTTP exception path.

The main work ahead is **consistency** (one JSON envelope and error contract everywhere), **edge-case correctness** (routing), and **honest limits** for production (rate limiting, multi-process behavior). None of that invalidates the direction.

---

## What is already strong

### 1. Clear positioning and documentation

The **README** is unusually good for a young framework: lifecycle diagram, parameter binding table, middleware semantics (including the “do not wrap `$next` in catch” note), DI registration patterns, and CLI usage. That reduces adoption friction and signals intent—many OSS PHP projects never reach this level of narrative.

### 2. Symfony as foundation (this is a feature, not “cheating”)

Using **HttpFoundation**, **Routing**, **Validator**, and **Console** is standard practice. Wrapping them behind `Bingo\Http\Request`, `Router`, and `DataTransferObject` is exactly how you avoid rebuilding low-value infrastructure. **Symfony DependencyInjection** behind your `Bingo\Container\Container` is a solid choice: you get compilation, autowiring for registered services, and room to grow without writing a second-rate container from scratch.

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

`composer test` runs a growing PHPUnit suite with broad coverage of container, router, middleware, DTOs, config, and exceptions. For a framework at this stage, that is a major credibility signal and safety net.

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

### P1 — `Router::isApiPath()` and empty `#[ApiController]` prefixes — **Done (superseded)**

**Status:** `isApiPath()` was **removed**. Unknown paths always throw framework HTTP exceptions and are handled by `ExceptionHandler` (same JSON envelope for all misses). The empty-prefix / “everything is API” bug no longer applies.

_Original note:_ `isApiPath()` treated an empty prefix as matching everything; bare `#[ApiController]` could force JSON 404/405 for every unknown URL.

---

### P2 — Error leakage in `Router` catch-all — **Done**

**Status:** The outer `catch (\Throwable)` **rethrows** (`throw $e;`). Nothing in that branch returns ad-hoc JSON with exception messages; `Application::handle()` keeps using the centralized exception handler.

_Original note:_ Broad catch could return exception text for API controllers and bypass `ExceptionHandler`.

---

### P2 — Rate limiting and multi-process deployments — **Done (documented; behavior unchanged)**

**Status:** Storage is still **static in-memory** (by design for now). **CLAUDE.md** and known-limitations call out **per-process / not distributed** limits. A Redis/APCu-backed implementation is still future work.

**Recommendation (remaining):** If you add production claims in marketing, repeat the same caveat or ship a pluggable store.

---

### P3 — Route registration order footgun — **Open**

**Status:** No automatic route sorting or `show:routes` dev warnings yet. Symfony `UrlMatcher` registration order + greedy `/{id}` is still a manual discipline (e.g. `/users/search` before `/{id}`).

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
| Documentation | **Strong** | **README** + **CLAUDE.md**; redundant `docs/*.md` files were removed. |
| Test discipline | **Strong** | PHPUnit coverage is a major asset. |

---

## Closing

For **day four of a beta**, this is an impressive skeleton: it already behaves like a small framework rather than a tutorial router. The “Laravel smell” is mostly **Eloquent and familiar folders**—that is a strategic choice, not an accident to be ashamed of. The highest-leverage next steps are **one JSON contract everywhere** and **tightening router edge cases**.

This file is a one-off review (`cursor-review.md`); fold any lasting decisions into **README** or **CLAUDE.md**.
