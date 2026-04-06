# Testing

Bingo projects use PHPUnit and the standard PSR-4 test layout. There is no special test runner — use Composer's `test` script.

---

## Running Tests

```bash
# Run the full test suite
composer test

# Run a specific test class
vendor/bin/phpunit --filter ContainerTest

# Run a specific test method
vendor/bin/phpunit --filter ContainerTest::testResolvesConcreteClass

# Run a directory
vendor/bin/phpunit tests/Unit/Bingo
```

---

## Test Layout

```text
tests/
├── Unit/
│   ├── Bingo/              # Framework-level unit tests
│   │   ├── ContainerTest.php
│   │   ├── RouterTest.php
│   │   └── …
│   └── App/                # Application-level unit tests
│       ├── Services/
│       └── DTOs/
└── Stubs/
    ├── Controllers/        # Stub controllers for router tests
    └── Services/           # Stub services for unit tests
```

Keep framework tests (`Unit/Bingo`) separate from application tests (`Unit/App`).

---

## Writing a Unit Test

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\App\Services;

use App\Services\UserService;
use App\Repositories\IUserRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class UserServiceTest extends TestCase
{
    private IUserRepository&MockObject $repository;
    private UserService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(IUserRepository::class);
        $this->service    = new UserService($this->repository);
    }

    public function test_create_user_returns_dto(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('create')
            ->willReturn(['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']);

        $dto = $this->service->createUser(/* … */);

        $this->assertSame(1, $dto->id);
        $this->assertSame('Alice', $dto->name);
    }
}
```

---

## Testing DTOs

```php
use App\DTOs\CreateUserDTO;
use Bingo\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class CreateUserDTOTest extends TestCase
{
    public function test_valid_data_passes(): void
    {
        $dto = CreateUserDTO::from([
            'email' => 'alice@example.com',
            'name'  => 'Alice',
            'age'   => 30,
        ]);

        $this->assertSame('alice@example.com', $dto->email);
    }

    public function test_invalid_email_throws(): void
    {
        $this->expectException(ValidationException::class);

        CreateUserDTO::fromRequest([
            'email' => 'not-an-email',
            'name'  => 'Alice',
        ]);
    }
}
```

---

## Testing the Container

```php
use Bingo\Container\Container;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    public function test_resolves_concrete_class(): void
    {
        $container = new Container();
        $service   = $container->get(MyService::class);

        $this->assertInstanceOf(MyService::class, $service);
    }

    public function test_singleton_returns_same_instance(): void
    {
        $container = new Container();
        $container->singleton(MyService::class);

        $a = $container->get(MyService::class);
        $b = $container->get(MyService::class);

        $this->assertSame($a, $b);
    }
}
```

---

## Stubs

Place stub controllers and services in `tests/Stubs/` for tests that need a known behaviour:

```php
// tests/Stubs/Controllers/PingController.php
namespace Tests\Stubs\Controllers;

use Bingo\Attributes\Route\ApiController;
use Bingo\Attributes\Route\Get;
use Bingo\Http\Response;

#[ApiController('/test')]
class PingController
{
    #[Get('/ping')]
    public function ping(): Response
    {
        return Response::json(['pong' => true]);
    }
}
```

---

## Conventions

- Test class names end in `Test`.
- Test method names start with `test_` (snake_case).
- Use `createMock()` and `createStub()` from PHPUnit for dependencies; avoid hitting real databases or network services in unit tests.
- Functional or integration tests that need the database should use SQLite in-memory (`:memory:`) to keep them fast and isolated.
