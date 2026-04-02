# Exception Handling

Uncaught throwables are converted into JSON responses by the exception handler.

## HTTP exceptions

Throw HTTP exceptions anywhere in your application:

```php
throw new NotFoundException('User not found');
throw new ConflictException('Email already exists');
throw new ForbiddenException('Insufficient scope');
```

## Default response shape

```json
{
  "statusCode": 404,
  "message": "User not found",
  "error": "Not Found"
}
```

## Validation exceptions

Validation failures return a 422 response with a field-to-message map.

## Debug mode

In debug mode, the framework returns the underlying exception message and includes diagnostic details.

In production, the message is generic and the internal details are omitted.

## Custom handler

Implement `Bingo\Contracts\ExceptionHandlerInterface` in your application and register it in `bootstrap/app.php` when you need a custom JSON structure or a custom error policy.
