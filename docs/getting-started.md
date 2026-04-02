# Getting Started

## Requirements

- PHP 8.5 or higher
- Composer
- SQLite, MySQL 8+, or PostgreSQL 14+

## Installation

```bash
git clone https://github.com/sadhakbj/bingo.git
cd bingo
composer install
cp .env.example .env
```

## Start the development server

```bash
php bin/bingo serve
```

The application is available at `http://127.0.0.1:8000`.

## First route

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

Request the endpoint with:

```bash
curl http://127.0.0.1:8000/api/hello
```

Routes are discovered automatically from controller attributes.

## Next steps

- Review [Routing](routing.md)
- Learn [Parameter Binding](parameter-binding.md)
- Explore [DTOs and Validation](dtos-and-validation.md)
