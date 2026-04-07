# Application Kernel Review And Refactor Plan

## Context

The stated direction for Bingo is closer to a NestJS-style PHP framework than a minimal micro-kernel:

- attribute-heavy developer experience
- strong framework conventions
- clear application lifecycle
- container-driven composition
- predictable bootstrapping
- good separation between configuration, discovery, provider registration, and request execution

`core/Bingo/Application.php` is currently the center of that lifecycle, so if this file is off, the whole framework feels off.

This document is an honest review of that file, why it currently feels misaligned with the framework’s intended direction, and how to refactor it using modern PHP practices and cleaner architectural boundaries.

The target assumption for the forward plan is PHP 8.5.

---

## Executive Summary

`Application.php` is readable and functional, but it is too eager, too central, and not cleanly layered.

The main architectural problem is that it mixes:

- environment bootstrapping
- configuration resolution
- discovery loading
- provider boot
- container setup
- router setup
- middleware setup
- exception policy
- HTTP runtime execution

inside one class, with most of it happening during construction.

That design makes the class convenient in the short term, but expensive in the long term because:

- override points happen too late
- lifecycle is hard to reason about
- composition is implicit instead of explicit
- some user-facing extension points are weaker than the docs imply
- responsibilities are blurred between app boot, app configuration, and request handling

The class is not “bad code”, but it is not yet a strong kernel design for the framework you are actually trying to build.

---

## What Feels Wrong Today

### 1. Construction and boot are collapsed into one step

The constructor currently:

- loads env vars
- loads config
- creates the container
- creates the router
- creates the middleware pipeline
- loads discovery metadata
- boots providers
- registers controllers
- stores discovered commands

That means `new Application(...)` is not just object construction. It is effectively a full boot sequence.

Why this is a problem:

- constructors should establish valid object state, not perform most of the framework lifecycle
- it becomes hard to customize boot order
- it reduces testability of intermediate phases
- it creates “too late to override” problems for bindings and middleware setup

This is the single biggest issue in the file.

### 2. The class violates single responsibility in a meaningful way

`Application` is acting as all of these:

- app factory
- app bootstrapper
- service registry facade
- middleware registry facade
- HTTP kernel
- exception resolution coordinator
- runtime entry point

That is too much for one class if the framework is supposed to scale in complexity.

In a NestJS-style system, the kernel should orchestrate specialized collaborators, not absorb all boot concerns directly.

### 3. Composition root behavior is not explicit enough

A framework core should make boot phases obvious:

1. load environment
2. load configuration
3. create container
4. register framework services
5. load discovery metadata
6. register providers
7. finalize router/middleware
8. accept requests

Right now that lifecycle exists, but it is hidden inside one constructor. That makes it harder to extend and harder to trust.

### 4. Some override points are semantically misleading

Because providers boot during construction, user code that runs after `Application::create()` can be too late to affect framework boot decisions.

That makes APIs like:

- `$app->instance(...)`
- `$app->bind(...)`
- `$app->use(...)`

less powerful than they appear, depending on what the framework has already done internally.

This is not only a cleanliness issue. It is an API honesty issue.

### 5. Path handling is not fully coherent

`Application` stores a base path, but other parts of the system still depend on the global `base_path()` helper.

That means the application instance does not fully own its own filesystem context.

For a framework kernel, that is a design leak.

### 6. Runtime normalization responsibilities are duplicated

Both the router and the application layer normalize responses.

That means the system is not fully clear about where the HTTP contract is finalized.

This is survivable, but it is a maintenance smell. Kernel boundaries should be sharper than this.

### 7. The class API is pragmatic, but not yet principled

The public surface is small, which is good. But the underlying design is still “service locator plus lifecycle side effects” more than “well-defined kernel”.

That matters if the framework wants to feel modern and deliberate rather than organically accumulated.

---

## Design Principles Being Underserved

### Single Responsibility Principle

`Application` has too many reasons to change:

- config changes
- discovery changes
- provider changes
- middleware changes
- runtime request handling changes
- exception handling changes

That is a clear SRP violation at the architectural level.

### Open/Closed Principle

The class is somewhat extensible through registration methods, but not strongly open for extension because lifecycle ordering is hardcoded inside the constructor.

### Dependency Inversion Principle

Some dependencies are properly abstracted, but the kernel still creates key concrete collaborators directly:

- `new Container()`
- `new Router(...)`
- `MiddlewarePipeline::create(...)`
- `new DiscoveryManager(...)`
- `new ExceptionHandler(...)`

That is understandable in early framework code, but it limits composability.

### Explicit Lifecycle Design

Modern framework cores benefit from lifecycle phases that can be reasoned about and tested independently.

This file currently hides lifecycle inside object creation.

### API Honesty

A good framework API should not imply “override me before runtime” if the internal runtime decisions have already been made.

This is one of the biggest gaps between the current file and a polished framework core.

---

## What A Better Direction Looks Like

For the kind of framework you described, `Application` should become an orchestrator, not a giant do-everything bootstrap object.

The intended shape should be closer to:

- `Application`
  high-level facade and runtime entry point
- `ApplicationBuilder` or `Bootstrapper`
  owns boot order and construction
- `EnvironmentLoader`
  loads `.env` and process env
- `ConfigurationBootstrapper`
  resolves typed config objects
- `DiscoveryBootstrapper`
  loads or rebuilds discovery metadata
- `ProviderBootstrapper`
  registers and boots providers
- `HttpKernel`
  handles requests and exceptions

This does not need to become enterprise ceremony. But it does need a cleaner separation between build-time composition and request-time execution.

---

## Refactor Goals

### Goal 1. Make lifecycle explicit

The framework should have obvious phases:

1. construct
2. configure
3. boot
4. run / handle

### Goal 2. Keep `Application` as the developer-facing facade

You do not need to expose five new core concepts to framework users.

Users can still write:

```php
$app = Application::create(basePath: dirname(__DIR__));
$app->bind(...);
$app->use(...);
$app->exceptionHandler(...);
$app->boot();
$app->run();
```

But internally that should be backed by a better lifecycle model.

### Goal 3. Make extension points real

If the framework says users can override bindings, logger, middleware, or exception handler before boot, that should be structurally true.

### Goal 4. Let the kernel own its paths

The application instance should be the authoritative source for:

- base path
- app path
- config path
- storage path
- public path
- bootstrap path

Global helpers can remain as convenience wrappers, but they should not be the true source of kernel behavior.

### Goal 5. Align with PHP 8.5 and modern style

PHP 8.5 should be used to improve clarity, not just version branding.

That means:

- stronger typing everywhere possible
- avoiding ambiguous mixed lifecycle state
- better readonly usage where object identity should not mutate
- clearer object composition over broad mutable classes

---

## Proposed Target Architecture

## 1. `Application` becomes a facade plus state holder

Responsibilities:

- expose the public framework API
- hold references to container, router, pipeline, config, kernel
- collect user customizations before boot
- delegate boot to a bootstrapper
- delegate request handling to an HTTP kernel

What should leave `Application`:

- most direct boot sequencing logic
- direct creation of discovery manager
- direct provider orchestration
- response normalization logic

## 2. Introduce an explicit boot phase

Recommended shape:

```php
$app = Application::create($basePath);
$app->bind(...);
$app->instance(...);
$app->use(...);
$app->exceptionHandler(...);
$app->boot();
$app->run();
```

Behavior:

- before `boot()`, registrations are allowed
- after `boot()`, the app is frozen for structural changes
- `run()` auto-boots if needed, or requires prior boot explicitly

Either model is acceptable, but the boot phase must exist conceptually.

## 3. Add a dedicated `HttpKernel`

`Application::handle()` currently contains request pipeline execution, response normalization, and exception fallback.

A dedicated `HttpKernel` should own:

- request handling
- pipeline execution
- response normalization
- exception handler delegation

That leaves `Application` thinner and makes the runtime path independently testable.

## 4. Treat discovery as boot-time infrastructure

Discovery should not feel like a hidden side effect in the constructor.

It should be part of boot:

- load discovery metadata
- register discovered controllers
- register discovered commands
- pass provider/binding metadata to bootstrapper

That keeps the mental model sane.

## 5. Separate framework defaults from application overrides

A NestJS-like feel depends heavily on predictable framework defaults plus deliberate override points.

The system should distinguish:

- framework defaults
- discovered framework providers
- discovered app providers
- explicit user overrides from bootstrap/app.php

The order should be intentional and documented.

---

## Concrete Refactor Plan

## Phase 1. Stabilize the current file without breaking behavior

Low-risk refactor:

- remove dead state like `$controllers` if it is not needed
- centralize response normalization into one method
- extract helper methods from the constructor
- make the constructor small and readable
- add tests around current boot order before deeper changes

Recommended result:

- constructor still boots, but the logic becomes named and testable

Why this phase matters:

- reduces immediate confusion
- gives a safe baseline for bigger changes

## Phase 2. Introduce explicit boot state

Add:

- `private bool $booted = false`
- `boot(): self`
- guards for mutating APIs after boot

Then move provider boot, discovery load, controller registration, and command registration into `boot()`.

Behavior option:

- `run()` calls `boot()` automatically if needed
- `handle()` may also call `boot()` automatically if desired

This phase gives the framework an honest lifecycle.

## Phase 3. Split runtime handling into `HttpKernel`

Create a kernel class responsible for:

- invoking middleware pipeline
- dispatching router
- normalizing responses
- delegating exceptions

Then `Application` becomes mostly:

- configure
- boot
- forward to kernel

This is the point where the architecture starts feeling intentional instead of accumulated.

## Phase 4. Replace global helper dependence in core boot paths

Core classes should stop depending on global `base_path()` as their source of truth.

Instead:

- `Application` owns path resolution
- collaborators receive resolved paths explicitly

For example:

- discovery cache dir from application paths
- app path from application paths
- storage path from application paths

Helpers can remain for userland convenience, but core boot should not depend on them.

## Phase 5. Clarify provider and override semantics

Define and enforce ordering:

1. environment/config load
2. core infrastructure objects created
3. user pre-boot overrides applied
4. discovery loaded
5. bindings registered
6. providers registered and booted
7. runtime kernel finalized

This phase is critical if you want the framework to feel trustworthy.

## Phase 6. Tighten contracts and typing for PHP 8.5

Improve signatures and intent:

- add more precise phpdoc generics where useful
- reduce mixed usage
- make internal mutability explicit
- prefer clearly named value objects / config objects over implicit arrays where lifecycle data is passed around

Also consider:

- a dedicated `ApplicationState` enum or lifecycle state indicator
- more readonly collaborators once boot is complete

---

## Suggested End State For `Application`

Not exact code, but conceptually:

```php
final class Application
{
    public readonly string $basePath;
    public readonly Container $container;
    public readonly Router $router;

    private bool $booted = false;
    private ?ExceptionHandlerInterface $exceptionHandler = null;

    public static function create(string $basePath): self;

    public function bind(string $abstract, ?string $concrete = null): self;
    public function singleton(string $abstract, ?string $concrete = null): self;
    public function instance(string $abstract, object $instance): self;
    public function use(mixed $middleware): self;
    public function exceptionHandler(ExceptionHandlerInterface $handler): self;

    public function boot(): self;
    public function handle(Request $request): HttpResponse;
    public function run(): void;
}
```

The important part is not the exact public methods. The important part is:

- construction is cheap
- boot is explicit
- runtime is separate
- overrides are real

---

## Practical Recommendations For Bingo Specifically

### Recommendation 1

Do not rewrite everything at once.

The right move is not a giant architecture rewrite. The right move is:

- stabilize behavior
- introduce lifecycle boundaries
- split responsibilities incrementally

### Recommendation 2

Prioritize lifecycle honesty over adding more features.

Framework users will forgive missing features sooner than they forgive confusing boot behavior.

### Recommendation 3

Treat `Application.php` as core framework architecture, not convenience glue.

This file is your kernel. It deserves stricter design standards than ordinary app code.

### Recommendation 4

Use the NestJS inspiration carefully.

You probably do not want to imitate NestJS literally. What you want from it is:

- clean module/system boundaries
- explicit lifecycle
- strong conventions
- discoverable developer experience
- container-driven orchestration

Those ideas translate well to PHP. The exact mechanics do not need to.

---

## Honest Closing Assessment

Your instinct is correct: `Application.php` is not yet expressing the framework you intend to build.

Right now it behaves more like:

- a convenient bootstrap object for a small framework

than like:

- a deliberate kernel for a modern attribute-heavy application framework

That is fixable.

The good news is that the file is still small enough to refactor cleanly. The architecture has not calcified yet. This is exactly the right time to tighten it.

The highest-value next move is:

1. introduce explicit boot lifecycle
2. separate HTTP runtime into a kernel
3. make override order structurally honest
4. stop relying on global helpers as the true core path source

If you do those well, the rest of the framework will feel much more coherent.
