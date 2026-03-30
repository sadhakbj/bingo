# Bingo — development roadmap

Opinionated sequence: stabilize foundations first, then features that differentiate the framework. This file is **forward-looking**; day-to-day accuracy for what already exists lives in **README.md** and **CLAUDE.md**.

---

## What Bingo is

PHP **8.5+**, API-first, small footprint. **Not** full Laravel or Symfony Framework. **NestJS-like** attributes and structure; **Symfony** for HTTP, routing, validation, console, and DI; **Eloquent** for persistence.

**Core bet:** attributes + great DX + optional OpenAPI later = a sensible default for JSON APIs and microservices.

---

## Packaging note

`core/` is intended to ship as a **separate Composer package**; the **application** (`app/`, `bootstrap/`, `config/`) is where users customize behavior. Exception / error response shape is overridden via `Core\Contracts\ExceptionHandlerInterface` — see `App\Exceptions\Handler` and `bootstrap/app.php` (commented example).

---

## Current foundation (done)

The items below are **implemented** in the repo today (verify in code if unsure):

| Area | Status |
|------|--------|
| Attribute routing (`#[Get]`, `#[Post]`, `#[ApiController]`, …) | Done |
| Parameter binding (`#[Body]`, `#[Query]`, `#[Param]`, `#[Headers]`, uploads, `#[Request]`) | Done |
| Global middleware pipeline with `$next` | Done |
| Route / controller `#[Middleware]` via nested pipeline | Done |
| DTOs + Symfony Validator + `ValidationException` / 422 | Done |
| `ApiResponse` envelope | Done |
| `DTOCollection` | Done |
| Eloquent + typed `DatabaseConfig` / `ConfigLoader` + `#[Env]` | Done |
| **DI container** (`Core\Container\Container`, Symfony `ContainerBuilder` + reflection fallback) | Done |
| Controllers + middleware resolved through container | Done |
| Global **exception handler** → JSON (`ExceptionHandler`) | Done |
| CLI `php bin/bingo` (serve, `show:routes`, `db:migrate`, generators) | Done |
| PHPUnit suite under `tests/` | Done |
| `UserService` + demo controllers using real DB patterns | Done |
| `LogMiddleware` / `AuthMiddleware` (Bearer check + `bearer_token` on request) | Implemented (auth is not JWT validation yet) |

### Known gaps (honest)

- **Rate limiting:** in-memory per process — document clearly; replace with Redis/APCu/symfony/rate-limiter for real production clusters.
- **Error JSON shape:** some paths (router 404/405, inline validation in `Router`, rate-limit body) still differ from `ApiResponse` — unify when convenient.
- **`isApiPath()`:** empty `#[ApiController]` prefix can mark every path as “API” for error formatting — avoid bare `#[ApiController]` without a prefix until fixed.
- **No controller auto-discovery** — still manual registration in `bootstrap/app.php`.

---

## Phase A — Polish & consistency (next)

Short, high-leverage work before big new systems:

1. **Single API error contract** — route all framework JSON errors through `ApiResponse` (or thin wrappers).
2. **Router edge cases** — empty API prefix behavior; optional route priority / ordering helpers for `/{id}` vs static segments.
3. **Docs sync** — this file + `CLAUDE.md` + README stay aligned after changes (no duplicate “known bugs” lists that contradict code).

---

## Phase B — OpenAPI (differentiator)

**Goal:** spec generated from existing attributes and DTO constraints.

- Paths/methods from route attributes; parameters from `#[Param]`, `#[Query]`, `#[Body]` types.
- Schemas from properties + Symfony `Assert\*`.
- Optional doc attributes (`summary`, `tags`, response DTO classes).
- Dev endpoints: e.g. `/openapi.json`, `/docs` (implementation choice TBD).

Add dependencies only when implementation starts (e.g. OpenAPI builder libraries).

---

## Phase C — Security layer

**Goal:** auth patterns that feel first-class, still composable.

- JWT validation guard (library TBD) and/or opaque token strategy.
- Nest-style **guards** (run in middleware pipeline with `$next`).
- Optional `#[AuthUser]` or request attribute conventions (already using `attributes` for Bearer token in `AuthMiddleware`).
- API keys for service-to-service; RBAC attributes if needed.

---

## Phase D — Developer experience

Partially satisfied by current **generators** (`generate:controller`, `g:service`, …). Remaining ideas:

- Optional **controller discovery** (opt-in scan of `app/Http/Controllers/`).
- **Resources / presenters** for complex JSON shaping (if not covered by output DTOs).
- Stricter static analysis (PHPStan) as a project standard.

---

## Phase E — Observability & production

- Structured logging (e.g. Monolog) with request ID correlation.
- Built-in **health** routes (`/health`, readiness hooks).
- **Real** rate limiting backend (see gaps above).
- Optional Prometheus-style metrics.

---

## Phase F — Events, queues, scheduling (when needed)

Only when a concrete use case appears:

- Domain events and handlers.
- Queue / worker command (e.g. Symfony Messenger or a thin wrapper).
- Cron-friendly `schedule:run` style command.

---

## Explicit non-goals (for now)

| Topic | Why out of scope |
|--------|------------------|
| WebSockets on FPM | Wrong runtime; separate service if needed. |
| Full OAuth2 authorization server | Use dedicated IdPs. |
| K8s/Helm in-repo | Document Docker; infra stays external. |

---

## Principles

1. **PHP 8.5+** — no older-version compromises in new code.
2. **Attributes over YAML/XML** for routes and validation wiring.
3. **Small dependency surface** — every package must earn its keep.
4. **Fail fast** — bad config at boot; validation at the boundary.
5. **JSON-first** APIs; align on one client-visible error shape over time.
6. **Test `core/`** — keep PHPUnit green when changing the framework.

---

## Success metrics (targets, not promises)

| Metric | Aim |
|--------|-----|
| Middleware overhead | Low single-digit ms on trivial routes |
| `core/` test safety net | Green CI on every change |
| Time to first CRUD demo | Minutes after clone (README path) |
| OpenAPI generation | Fast enough for dev reload (when built) |

---

*Older versions of this document described stub services, no DI, `database/migrate.php`, and route middleware without `$next`. Those issues are obsolete; if you see them elsewhere, update or delete that source.*
