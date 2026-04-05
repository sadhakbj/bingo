# Exception Handling

Every uncaught `Throwable` is caught by Bingo and converted into a JSON response before it reaches the client. In debug mode the raw message is included; in production a generic message is returned.

---

## Default Response Shape

```json
{
  "statusCode": 404,
  "message": "User not found",
  "error": "Not Found"
}
```

| Field | Source |
|---|---|
| `statusCode` | HTTP status code from the exception |
| `message` | Exception message (generic in production for non-HTTP exceptions) |
| `error` | Standard HTTP status phrase from Symfony `Response::$statusTexts` |

---

## Built-In HTTP Exceptions

Throw any of these from controllers, services, or middleware. Each maps to the expected HTTP status code.

All exceptions live in `Bingo\Exceptions\Http\`.

| Exception class | Status code |
|---|---|
| `BadRequestException` | 400 |
| `UnauthorizedException` | 401 |
| `ForbiddenException` | 403 |
| `NotFoundException` | 404 |
| `MethodNotAllowedException` | 405 |
| `NotAcceptableException` | 406 |
| `RequestTimeoutException` | 408 |
| `ConflictException` | 409 |
| `GoneException` | 410 |
| `PayloadTooLargeException` | 413 |
| `UnsupportedMediaTypeException` | 415 |
| `UnprocessableEntityException` | 422 |
| `TooManyRequestsException` | 429 |
| `InternalServerErrorException` | 500 |
| `NotImplementedException` | 501 |
| `BadGatewayException` | 502 |
| `ServiceUnavailableException` | 503 |
| `GatewayTimeoutException` | 504 |
| `HttpVersionNotSupportedException` | 505 |
| `ImATeapotException` | 418 |
| `PreconditionFailedException` | 412 |

### Examples

```php
use Bingo\Exceptions\Http\NotFoundException;
use Bingo\Exceptions\Http\ConflictException;
use Bingo\Exceptions\Http\UnauthorizedException;
use Bingo\Exceptions\Http\ForbiddenException;
use Bingo\Exceptions\Http\BadRequestException;

// 404
throw new NotFoundException('User not found');

// 409
throw new ConflictException('Email address is already taken');

// 401
throw new UnauthorizedException('Token has expired');

// 403
throw new ForbiddenException('You do not have access to this resource');

// 400
throw new BadRequestException('Invalid request payload');
```

---

## Validation Exceptions

Validation failures from `#[Body]` DTOs are automatically converted to `422` responses with a field error map:

```json
{
  "statusCode": 422,
  "message": {
    "email": "This value is not a valid email address.",
    "name": "This value should not be blank."
  },
  "error": "Unprocessable Content"
}
```

---

## Debug Mode

When `APP_DEBUG=true`, the raw exception message is always included in the response, and additional details may be exposed. Set `APP_DEBUG=false` in production.

---

## Custom Exception Handler

Implement `Bingo\Contracts\ExceptionHandlerInterface` to control the entire exception-to-response transformation:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Bingo\Contracts\ExceptionHandlerInterface;
use Bingo\Exceptions\Http\HttpException;
use Bingo\Http\Response;
use Bingo\Validation\ValidationException;
use Throwable;

class Handler implements ExceptionHandlerInterface
{
    public function __construct(private readonly bool $debug) {}

    public function handle(Throwable $e): Response
    {
        if ($e instanceof ValidationException) {
            return Response::json([
                'success'  => false,
                'errors'   => $e->getErrors(),
                'message'  => 'Validation failed',
            ], 422);
        }

        if ($e instanceof HttpException) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'code'    => $e->getStatusCode(),
            ], $e->getStatusCode());
        }

        $message = $this->debug ? $e->getMessage() : 'An unexpected error occurred';

        return Response::json([
            'success' => false,
            'message' => $message,
        ], 500);
    }
}
```

Register it in `bootstrap/app.php`:

```php
$app->exceptionHandler(new \App\Exceptions\Handler($app->debug));
```

The framework ships with a default handler at `App\Exceptions\Handler` that you can edit directly.

---

## Generating an Exception Class

```bash
php bin/bingo g:exception PaymentDeclinedException --status=402
```

This creates `app/Exceptions/PaymentDeclinedException.php` extending `HttpException` with the provided status code.
