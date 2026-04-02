# Routing

Routes are declared on controller methods using PHP attributes.

## Basic example

```php
use Bingo\Attributes\Route\ApiController;
use Bingo\Attributes\Route\Delete;
use Bingo\Attributes\Route\Get;
use Bingo\Attributes\Route\Post;
use Bingo\Attributes\Route\Put;

#[ApiController('/users')]
class UsersController
{
    #[Get('/')]
    public function index(): Response {}

    #[Get('/{id}')]
    public function show(#[Param('id')] int $id): Response {}

    #[Post('/')]
    public function create(#[Body] CreateUserDTO $dto): Response {}

    #[Put('/{id}')]
    public function update(#[Param('id')] int $id, #[Body] UpdateUserDTO $dto): Response {}

    #[Delete('/{id}')]
    public function destroy(#[Param('id')] int $id): Response {}
}
```

## Supported HTTP attributes

- `#[Get]`
- `#[Post]`
- `#[Put]`
- `#[Patch]`
- `#[Delete]`
- `#[Head]`
- `#[Options]`
- `#[Route('/path', 'METHOD')]`

## Route order

Declare specific routes before wildcard routes inside the same controller.

For example, place `/search` before `/{id}` so the specific path is matched first.

## Response metadata

The router supports response status and headers through `#[HttpCode]` and `#[Header]`.

### `#[HttpCode]`

- Applies when the response still has the default status code.
- Method-level values override class-level values.
- If the controller already returns a response with a status code, the attribute does not override it.

### `#[Header]`

- Adds response headers.
- Class-level headers are applied first.
- Method-level headers override the same name on the class.
- If the response already contains a header, the response value wins.

## Example

```php
#[ApiController('/reports')]
#[Header('X-API-Version', '1')]
class ReportsController
{
    #[Get('/export')]
    #[HttpCode(202)]
    #[Header('X-API-Version', '2')]
    public function export(): Response
    {
        return Response::json(['state' => 'processing']);
    }
}
```
