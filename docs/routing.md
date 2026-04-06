# Routing

Routes are declared directly on controller methods using PHP attributes. No manual route registration is required — the framework discovers controllers and their routes automatically.

---

## Controllers

### `#[ApiController]`

Mark a class with `#[ApiController]` to tell the discovery system it is a routable controller. An optional prefix is applied to all routes defined in the class.

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Bingo\Attributes\Route\ApiController;
use Bingo\Attributes\Route\Get;
use Bingo\Http\Response;

#[ApiController('/api/v1')]
class ProductsController
{
    #[Get('/products')]        // → GET /api/v1/products
    public function index(): Response { /* … */ }

    #[Get('/products/{id}')]   // → GET /api/v1/products/{id}
    public function show(): Response { /* … */ }
}
```

Controllers with no prefix (`#[ApiController]` or `#[ApiController('')]`) use the route paths as-is.

---

## HTTP Verb Attributes

| Attribute | HTTP method |
|---|---|
| `#[Get('/path')]` | GET |
| `#[Post('/path')]` | POST |
| `#[Put('/path')]` | PUT |
| `#[Patch('/path')]` | PATCH |
| `#[Delete('/path')]` | DELETE |
| `#[Head('/path')]` | HEAD |
| `#[Options('/path')]` | OPTIONS |
| `#[Route('/path', 'METHOD')]` | any method |

All verb attributes live in `Bingo\Attributes\Route`.

### Generic `#[Route]`

Use `#[Route]` when you need a custom or non-standard method:

```php
use Bingo\Attributes\Route\Route;

#[Route('/webhook', 'POST')]
public function webhook(): Response { /* … */ }
```

---

## A Complete CRUD Controller

```php
use Bingo\Attributes\Route\{ApiController, Get, Post, Put, Patch, Delete};
use Bingo\Attributes\Route\{Body, Param, Query};

#[ApiController('/users')]
class UsersController
{
    #[Get('/')]
    public function index(
        #[Query('page')]  int $page  = 1,
        #[Query('limit')] int $limit = 20,
    ): Response { /* … */ }

    #[Get('/{id}')]
    public function show(#[Param('id')] int $id): Response { /* … */ }

    #[Post('/')]
    public function create(#[Body] CreateUserDTO $dto): Response { /* … */ }

    #[Put('/{id}')]
    public function update(
        #[Param('id')]  int $id,
        #[Body] UpdateUserDTO $dto,
    ): Response { /* … */ }

    #[Delete('/{id}')]
    public function destroy(#[Param('id')] int $id): Response { /* … */ }
}
```

---

## Route Order

Specific routes must be declared **before** wildcard routes inside the same controller. The router matches in declaration order.

```php
#[Get('/search')]   // ✅ declared first — matches GET /users/search
public function search(): Response { /* … */ }

#[Get('/{id}')]     // wildcard — matches GET /users/{anything}
public function show(): Response { /* … */ }
```

If `/{id}` is placed first, a request to `/users/search` is matched by the wildcard instead of the specific action.

---

## Response Metadata

The router applies `#[HttpCode]` and `#[Header]` attributes after the action returns. These are useful for documenting and enforcing response contracts without duplicating code inside every action.

### `#[HttpCode]`

Sets the response status code when the action returns with the default `200` status. If the action already returns a response with a non-default status, the attribute is ignored.

- Method-level values override class-level values.

```php
use Bingo\Attributes\Route\HttpCode;

#[Post('/')]
#[HttpCode(201)]
public function create(#[Body] CreateUserDTO $dto): Response
{
    return Response::json($this->service->create($dto));
    // Status code is 201 even though json() defaults to 200.
}
```

### `#[Header]`

Adds a header to the response. Class-level headers are applied first; method-level headers override headers of the same name.

If the response already contains a header with that name, the response value takes precedence.

```php
use Bingo\Attributes\Route\Header;

#[ApiController('/reports')]
#[Header('X-API-Version', '1')]
class ReportsController
{
    #[Get('/summary')]
    public function summary(): Response { /* … */ }   // → X-API-Version: 1

    #[Get('/export')]
    #[HttpCode(202)]
    #[Header('X-API-Version', '2')]
    public function export(): Response { /* … */ }    // → X-API-Version: 2
}
```

---

## Route Parameters

Route parameters are enclosed in curly braces and extracted with `#[Param]`:

```php
#[Get('/orders/{orderId}/items/{itemId}')]
public function showItem(
    #[Param('orderId')] int $orderId,
    #[Param('itemId')]  int $itemId,
): Response { /* … */ }
```

See [Parameter Binding](parameter-binding.md) for full details on all binding attributes.
