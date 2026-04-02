# Bingo

Bingo is a PHP 8.5+ framework for building API-first applications.

It combines attribute-based routing, automatic controller discovery, typed configuration, dependency injection, validation, and Eloquent ORM support in a single framework built on top of Symfony components.

## What makes Bingo different

- Controllers and routes are discovered automatically from PHP attributes.
- Configuration is typed and injected through `#[Env]` attributes.
- Request data is bound directly into method parameters and DTOs.
- Middleware, rate limiting, logging, and exception handling are built in.
- Eloquent ORM works out of the box.
- The CLI includes discovery, generation, and database commands.

## Documentation

- [Getting Started](docs/getting-started.md)
- [Introduction](docs/introduction.md)
- [Configuration](docs/configuration.md)
- [Routing](docs/routing.md)
- [Auto-Discovery](docs/auto-discovery.md)
- [Parameter Binding](docs/parameter-binding.md)
- [Middleware](docs/middleware.md)
- [DTOs and Validation](docs/dtos-and-validation.md)
- [Dependency Injection](docs/dependency-injection.md)
- [Rate Limiting](docs/rate-limiting.md)
- [Server-Sent Events](docs/server-sent-events.md)
- [Logging](docs/logging.md)
- [Exception Handling](docs/exception-handling.md)
- [Eloquent ORM](docs/eloquent-orm.md)
- [CLI](docs/cli.md)
- [Testing](docs/testing.md)
- [Deployment](docs/deployment.md)
- [Project Structure](docs/project-structure.md)

## Requirements

- PHP 8.5 or higher
- Composer
- SQLite, MySQL 8+, or PostgreSQL 14+

## Quick Start

```bash
git clone https://github.com/sadhakbj/bingo.git
cd bingo
composer install
cp .env.example .env
php bin/bingo serve
```

Create your first controller:

```php
namespace App\Http\Controllers;

use Bingo\Attributes\Route\ApiController;
use Bingo\Attributes\Route\Get;
use Bingo\Http\Response;

#[ApiController('/api')]
class HelloController
{
    #[Get('/hello')]
    public function hello(): Response
    {
        return Response::json(['message' => 'Hello from Bingo']);
    }
}
```

Visit `GET /api/hello` and the route will be discovered automatically.
