# Dependency Injection

Bingo's DI container wraps Symfony `ContainerBuilder` with reflection-based fallback autowiring. Concrete classes with typed constructors are resolved automatically with zero configuration.

---

## Automatic Resolution

Any class with a typed constructor can be injected anywhere — controllers, services, middleware, commands:

```php
class ReportsController
{
    public function __construct(
        private readonly ReportService     $reports,
        private readonly AppConfig         $config,
        private readonly LoggerInterface   $logger,
    ) {}
}
```

The container walks the constructor, resolves each dependency recursively, and caches the result.

---

## Interface Bindings

### Using the `#[Bind]` Attribute

Place `#[Bind(ConcreteClass::class)]` on an interface to register the binding automatically during discovery:

```php
use Bingo\Attributes\Provider\Bind;

#[Bind(UserRepository::class)]
interface IUserRepository
{
    public function findById(int $id): ?User;
    public function all(): array;
}
```

The concrete implementation is then injected wherever `IUserRepository` is type-hinted.

### Explicit Bindings in `bootstrap/app.php`

For one-off or conditional wiring:

```php
// Resolve a new instance each time
$app->bind(ReportBuilderInterface::class, PdfReportBuilder::class);

// Resolve once and share the instance (singleton)
$app->singleton(MailerInterface::class, SmtpMailer::class);

// Register a pre-built object
$app->instance(PaymentConfig::class, new PaymentConfig(key: env('STRIPE_KEY')));
```

---

## Service Providers

Service providers are discovered automatically from `app/` when annotated with `#[ServiceProvider]`. Use them to wire services that need custom construction logic.

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Bingo\Attributes\Provider\ServiceProvider;
use Bingo\Attributes\Provider\Singleton;

#[ServiceProvider]
class AppServiceProvider
{
    #[Singleton]
    public function stripeClient(): StripeClient
    {
        return new StripeClient(env('STRIPE_KEY'));
    }
}
```

| Attribute | Effect |
|---|---|
| `#[ServiceProvider]` | Marks the class for discovery; the framework instantiates it and calls its methods |
| `#[Singleton]` | The return value is stored and reused for the lifetime of the container |

---

## Container API

The container is available from `bootstrap/app.php` via the `$app` variable:

```php
// Bind an interface to a concrete class (transient)
$app->bind(CacheInterface::class, RedisCache::class);

// Bind as singleton (one instance per container lifetime)
$app->singleton(QueueInterface::class, SqsQueue::class);

// Register a pre-built instance
$app->instance(HttpClient::class, new HttpClient(base_uri: 'https://api.example.com'));
```

After `$app->run()` (or `Application::create()`), the container is compiled. Register all services **before** the application runs.

---

## Injecting the Container Itself

If you need to resolve services dynamically at runtime, type-hint `Psr\Container\ContainerInterface`:

```php
use Psr\Container\ContainerInterface;

class HandlerLocator
{
    public function __construct(private readonly ContainerInterface $container) {}

    public function get(string $id): object
    {
        return $this->container->get($id);
    }
}
```

---

## Config Classes

The typed config classes in `config/` (`AppConfig`, `DbConfig`, `LogConfig`, etc.) are pre-registered as singletons and can be injected anywhere:

```php
class SomeService
{
    public function __construct(
        private readonly AppConfig       $app,
        private readonly RateLimitConfig $rateLimit,
    ) {}
}
```

See [Configuration](configuration.md) for all available config classes.
