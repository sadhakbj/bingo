# DI Container Implementation Plan

## Why / What Problem We're Solving

Bingo currently instantiates everything with `new $class()` — controllers, middlewares, services. This means controllers like `UsersController` manually call `new UserService()` inline, which is untestable, tightly coupled, and un-NestJS.

**Goal:** Build a DI container with full autowiring so that this just works:

```php
class PostsController {
    public function __construct(
        private readonly PostService $postService  // injected automatically — zero config
    ) {}
}

class PostService {
    public function __construct(
        private readonly UserService $userService  // also auto-injected — zero config
    ) {}
}
```

No registration. No boilerplate. Just type-hint and go.

---

## Engine: `symfony/dependency-injection`

We already use 4 Symfony packages (`http-foundation`, `routing`, `validator`, `console`). Adding `symfony/dependency-injection ^8.0` is completing the set — not introducing a foreign dependency. Users never touch raw Symfony DI. Bingo wraps it in a clean API.

---

## The Golden Rule: When Do You Actually Register Something?

**Almost never.** The reflection fallback auto-resolves any concrete class. Only two situations require explicit registration:

```php
// 1. Interface → concrete (reflection can't guess which impl you want)
$app->singleton(CacheInterface::class, RedisCache::class);
$app->bind(MailerInterface::class, SmtpMailer::class);

// 2. Scalar/config values (reflection can't guess a string or int)
$app->instance(Config::class, new Config(['db_host' => 'localhost']));
```

**`PostService`, `UserService`, `OrderService`, `NotificationService`** — create the file, type-hint it in a constructor, done. No registration. This is closer to Laravel than NestJS (NestJS requires `providers: [PostService]` in every module).

---

## Architecture: Two-Layer Resolution

```
container->make(UsersController::class)
    │
    ├─ Registered in Symfony DI? ──YES──► Symfony manages lifecycle (singleton/transient)
    │
    └─ NOT registered? ──────────────────► Reflection fallback
                                               │
                                               └─ Constructor param: UserService $svc
                                                   │
                                                   └─ container->get(UserService::class)
                                                       │
                                                       ├─ Registered? → Symfony singleton ✓
                                                       └─ Not registered? → Reflection again ✓
```

Reflection calls back into the container recursively. So even when using the fallback, registered singletons are respected — you get the same instance.

---

## Request Lifecycle with DI

```
bootstrap/app.php registers services (optional)
        ↓
$app->run()
        ↓
container->compile()  [Symfony DI freezes — one-time cost]
        ↓
HTTP request arrives
        ↓
Router: container->make(UsersController::class)
        ↓
Reflection: needs UserService → container->get(UserService::class) → singleton
        ↓
Controller instantiated with injected deps
        ↓
Response returned
```

---

## Files to Create

| File | Description |
|------|-------------|
| `core/Container/ContainerException.php` | PSR-11 `ContainerExceptionInterface` |
| `core/Container/NotFoundException.php` | PSR-11 `NotFoundExceptionInterface` |
| `core/Container/Container.php` | **The engine** — see below |
| `core/Attributes/Injectable.php` | `#[Injectable]` marker attribute (transient scope) |
| `core/Attributes/Singleton.php` | `#[Singleton]` marker attribute (future auto-scanning) |
| `tests/Unit/Core/Container/ContainerTest.php` | Full test coverage |
| `tests/Stubs/Services/StubService.php` | Stub classes for container tests |

## Files to Modify

| File | Change |
|------|--------|
| `composer.json` | Add `"symfony/dependency-injection": "^8.0"` |
| `core/Application.php` | Add container + proxy methods + wire into Router/Pipeline |
| `core/Router/Router.php` | Accept `?Container $container = null`; use for controller + middleware instantiation |
| `core/Http/Middleware/MiddlewarePipeline.php` | Add `?Container`; use in `resolveMiddleware()`; add `setContainer()` |
| `bootstrap/app.php` | Add DI registration section (commented example) |

---

## Container.php — Public API

```php
class Container implements ContainerInterface  // PSR-11
{
    // Registration (call these in bootstrap, before run())
    singleton(string $abstract, ?string $concrete = null): void
    bind(string $abstract, ?string $concrete = null): void
    instance(string $abstract, object $obj): void

    // Resolution
    get(string $id): mixed    // PSR-11
    has(string $id): bool     // PSR-11
    make(string $id): mixed   // alias for get()

    // Lifecycle
    compile(): void           // idempotent; called automatically in run()
}
```

**Internal resolution order in `get()`:**
1. `$instances[]` — pre-built objects (always wins)
2. Symfony compiled container — registered singletons/bindings
3. Reflection fallback — any concrete class, zero config

---

## Container.php — Key Implementation Details

```php
// Every Definition gets autowiring so Symfony injects typed deps
$definition = new Definition($concrete);
$definition->setShared(true);        // singleton
$definition->setAutowired(true);     // Symfony DI injects constructor deps

// Reflection fallback with circular-dep detection
private array $resolving = [];       // detection stack

private function resolveViaReflection(string $class): object
{
    if (isset($this->resolving[$class])) {
        throw new ContainerException("Circular dependency: ... → {$class}");
    }
    $this->resolving[$class] = true;
    try {
        $constructor = (new ReflectionClass($class))->getConstructor();
        // for each param:
        //   typed non-builtin → $this->get($typeName)   ← recursive, respects singletons
        //   optional          → $param->getDefaultValue()
        //   nullable          → null
        //   else              → throw ContainerException
        return $reflection->newInstanceArgs($args);
    } finally {
        unset($this->resolving[$class]);   // always cleans up
    }
}
```

---

## Router.php Changes

```php
// Constructor — nullable preserves all existing tests (new Router() with 0 args)
public function __construct(private readonly ?Container $container = null)

// Line 184 — controller instantiation
$controller = $this->container !== null
    ? $this->container->make($controllerClass)
    : new $controllerClass();

// Line 334 — route middleware instantiation
$pipeline->add(
    $this->container !== null
        ? $this->container->make($middlewareClass)
        : new $middlewareClass()
);
```

---

## MiddlewarePipeline.php Changes

```php
private ?Container $container = null;

// Updated factory — passes container through
public static function create(?Container $container = null): self
{
    $instance = new self();
    $instance->container = $container;
    return $instance;
}

// Required because setDefaultMiddleware() in Application replaces $this->pipeline
// via static factory (defaultApi()/productionApi()) — container must be re-injected after
public function setContainer(?Container $container): self
{
    $this->container = $container;
    return $this;
}

// resolveMiddleware() string branch becomes:
return $this->container !== null ? $this->container->make($middleware) : new $middleware();
```

---

## Application.php Changes

```php
private Container $container;

// __construct():
$this->container = new Container();
$this->router    = new Router($this->container);
$this->pipeline  = MiddlewarePipeline::create($this->container);
// setDefaultMiddleware() replaces $this->pipeline internally — re-inject after:
// (inside setDefaultMiddleware, after assignment):
$this->pipeline->setContainer($this->container);

// run() — compile before first request:
public function run(): void
{
    $this->container->compile();
    $request  = Request::createFromGlobals();
    $response = $this->handle($request);
    $response->send();
}

// New chainable proxy methods:
public function singleton(string $abstract, ?string $concrete = null): self
public function bind(string $abstract, ?string $concrete = null): self
public function instance(string $abstract, object $instance): self
public function make(string $abstract): mixed
public function getContainer(): Container
```

---

## bootstrap/app.php — New DI Section

```php
/*
|--------------------------------------------------------------------------
| Register Services (Dependency Injection)
|--------------------------------------------------------------------------
| Only needed for:
|   - Interface → concrete bindings
|   - Pre-built config/scalar objects
|
| Concrete classes with typed constructor deps are auto-resolved — no registration needed.
|
| $app->singleton(CacheInterface::class, RedisCache::class);
| $app->bind(MailerInterface::class, SmtpMailer::class);
| $app->instance(Config::class, new Config([...]));
*/
```

---

## Test Coverage (ContainerTest.php)

```
Binding & Resolution:
  ✓ test_singleton_returns_same_instance
  ✓ test_bind_returns_new_instance
  ✓ test_instance_returns_prebuilt_object
  ✓ test_singleton_with_interface_mapping
  ✓ test_bind_with_interface_mapping
  ✓ test_has_for_registered_service
  ✓ test_has_for_concrete_unregistered_class
  ✓ test_has_false_for_interface_without_binding
  ✓ test_make_is_alias_for_get

Reflection Fallback:
  ✓ test_reflection_resolves_zero_arg_class
  ✓ test_reflection_resolves_typed_dependency
  ✓ test_reflection_prefers_registered_singleton    ← critical integration test
  ✓ test_reflection_resolves_optional_params
  ✓ test_reflection_resolves_nullable_params

Error Cases:
  ✓ test_circular_dependency_throws_ContainerException
  ✓ test_nonexistent_class_throws_NotFoundException
  ✓ test_abstract_class_throws_NotFoundException
  ✓ test_interface_without_binding_throws_NotFoundException
  ✓ test_primitive_param_throws_ContainerException
  ✓ test_register_after_compile_throws_ContainerException
  ✓ test_compile_is_idempotent
```

**Stub classes** in `tests/Stubs/Services/StubService.php`:
- `StubService` — zero-arg constructor
- `StubServiceWithDep` — `__construct(StubService $service)`
- `StubServiceWithPrimitive` — `__construct(string $name)` (no default — unresolvable)

---

## Verification

```bash
# Install
composer require symfony/dependency-injection:^8.0

# All existing + new tests must be green
php vendor/bin/phpunit

# End-to-end: UserService injected into UsersController automatically
curl -X POST http://localhost:8000/users \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice","email":"alice@example.com"}'
# → 201 Created, real DB row, UserService injected with zero manual registration
```

---

## What's NOT in Phase 1

- `#[Singleton]` / `#[Injectable]` **auto-scanning** — attributes are markers only; no directory scanning yet
- **Compiled container dumping** to PHP file (production cache) — Phase 2
- **REQUEST scope** (per-request instances) — Phase 2
- **Service providers / modules** (NestJS-style module grouping) — Phase 2
- **Tagged services** (collect all implementations of an interface) — Phase 2
