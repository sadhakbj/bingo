# Getting Started

---

## Requirements

| Requirement | Minimum version |
|---|---|
| PHP | 8.4 |
| Composer | 2.x |
| Database | SQLite 3, MySQL 8+, or PostgreSQL 14+ |

Optional:
- `ext-redis` (phpredis) for distributed rate limiting in production

---

## Installation

```bash
git clone https://github.com/sadhakbj/bingo.git my-api
cd my-api
composer install
cp .env.example .env
```

Edit `.env` to match your local environment (see [Configuration](configuration.md) for all available keys).
If you prefer, you can also export the same variables in your shell instead of using a `.env` file.

For Docker or Kubernetes deployments, you can inject environment variables directly
through the platform instead of shipping a `.env` file.

---

## Start the Development Server

```bash
php bin/bingo serve
```

The server listens on `http://127.0.0.1:8000` by default.

```bash
# Custom host and port
php bin/bingo serve --host=0.0.0.0 --port=9000
```

Bingo logs every registered route to the console on startup.

---

## Your First Controller

Create `app/Http/Controllers/HelloController.php`:

```php
<?php

declare(strict_types=1);

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
        return Response::json(['message' => 'Hello from Bingo!']);
    }
}
```

The route is discovered automatically — no registration needed. Test it:

```bash
curl http://127.0.0.1:8000/api/hello
```

```json
{"message":"Hello from Bingo!"}
```

---

## Your First POST Route

```php
use Bingo\Attributes\Route\Post;
use Bingo\Attributes\Route\Body;

#[Post('/greet')]
public function greet(#[Body] GreetDTO $dto): Response
{
    return Response::json(['greeting' => "Hello, {$dto->name}!"]);
}
```

```php
// app/DTOs/GreetDTO.php
use Bingo\Data\DataTransferObject;
use Symfony\Component\Validator\Constraints as Assert;

class GreetDTO extends DataTransferObject
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    public readonly string $name;
}
```

```bash
curl -X POST http://127.0.0.1:8000/api/greet \
     -H 'Content-Type: application/json' \
     -d '{"name": "World"}'
```

---

## Running Database Migrations

```bash
php bin/bingo db:migrate
```

Migration files live in `database/migrations/`. They are executed in alphabetical order.

---

## Verify Registered Routes

```bash
php bin/bingo show:routes
```

---

## Next Steps

- [Routing](routing.md) — all HTTP verb attributes and prefix rules
- [Parameter Binding](parameter-binding.md) — route params, query strings, file uploads
- [DTOs and Validation](dtos-and-validation.md) — request validation with Symfony constraints
- [Configuration](configuration.md) — typed config classes and environment variables
