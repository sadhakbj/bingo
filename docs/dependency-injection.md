# Dependency Injection

Bingo includes a dependency injection container with automatic resolution for concrete classes.

## Zero-configuration resolution

Classes with type-hinted constructors can be resolved automatically.

```php
class UsersController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly LoggerInterface $logger,
    ) {}
}
```

## Binding interfaces

Use `#[Bind]` on the interface to associate it with a concrete implementation.

```php
#[Bind(UserRepository::class)]
interface IUserRepository
{
    public function findById(int $id): ?User;
}
```

The framework scans application classes and registers the binding automatically.

## Service providers

Service providers are used for values that need manual construction.

```php
#[ServiceProvider]
class AppServiceProvider
{
    #[Singleton]
    public function stripeClient(): StripeClient
    {
        return new StripeClient(env('STRIPE_KEY'));
    }

    #[Boots]
    public function boot(StripeClient $stripe): void
    {
        $stripe->setApiVersion('2024-06-20');
    }
}
```

## Explicit bindings

For one-off wiring, you can register services in `bootstrap/app.php`.

```php
$app->singleton(MailerInterface::class, SmtpMailer::class);
$app->bind(ReportBuilder::class);
$app->instance(PaymentConfig::class, new PaymentConfig(key: env('STRIPE_KEY')));
```
