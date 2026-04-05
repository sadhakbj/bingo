# Responses

Bingo provides several response helpers that wrap Symfony's `Response` class. All controller actions are expected to return a `Bingo\Http\Response` (or `StreamedResponse`).

---

## `Response::json()`

Returns a JSON response.

```php
use Bingo\Http\Response;

// 200 OK
return Response::json(['users' => $users]);

// 201 Created
return Response::json(['id' => $user->id], 201);

// Custom headers
return Response::json($data, 200, ['X-Custom' => 'value']);
```

Signature:

```php
Response::json(mixed $data, int $status = 200, array $headers = []): Response
```

---

## `ApiResponse` Envelope

`Bingo\DTOs\Http\ApiResponse` provides a structured JSON envelope for API responses. Use it to return consistent shapes across your application.

### Success

```php
use Bingo\DTOs\Http\ApiResponse;

return Response::json(
    ApiResponse::success(data: $userDTO->toArray())->toArray()
);

// With status code and message
return Response::json(
    ApiResponse::success(
        data: $userDTO->toArray(),
        message: 'User created successfully',
        statusCode: 201,
    )->toArray(),
    201,
);

// With pagination metadata
return Response::json(
    ApiResponse::success(
        data: $users,
        meta: ['total' => 100, 'page' => 1, 'per_page' => 20],
    )->toArray()
);
```

### Error Helpers

```php
// Generic error
ApiResponse::error('Something went wrong', errors: ['field' => 'message'], statusCode: 400);

// Validation failure (422)
ApiResponse::validation(['email' => 'Invalid email address']);

// Not found (404)
ApiResponse::notFound('User not found');

// Unauthorized (401)
ApiResponse::unauthorized('Token expired');

// Forbidden (403)
ApiResponse::forbidden('Insufficient permissions');
```

### Response Shape

```json
{
  "success": true,
  "message": "User created successfully",
  "data": { "id": 1, "name": "Alice" },
  "errors": null,
  "meta": null,
  "status_code": 201,
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

| Field | Type | Description |
|---|---|---|
| `success` | bool | `true` when `status_code < 400` |
| `message` | string | Human-readable status message |
| `data` | mixed | Response payload |
| `errors` | array\|null | Field-level error map (validation) |
| `meta` | array\|null | Pagination, cursors, or other metadata |
| `status_code` | int | HTTP status code |
| `timestamp` | string | ISO 8601 timestamp of when the response was created |

### `ApiResponse` Method Reference

| Method | Status | Description |
|---|---|---|
| `ApiResponse::success($data, $message, $statusCode, $meta)` | 200 (default) | Successful response |
| `ApiResponse::error($message, $errors, $statusCode, $data)` | 400 (default) | Error response |
| `ApiResponse::validation($errors, $message)` | 422 | Validation failure |
| `ApiResponse::notFound($message)` | 404 | Resource not found |
| `ApiResponse::unauthorized($message)` | 401 | Authentication required |
| `ApiResponse::forbidden($message)` | 403 | Access denied |

---

## Status Codes

Use Symfony's `Response::HTTP_*` constants for readability:

```php
use Symfony\Component\HttpFoundation\Response;

return \Bingo\Http\Response::json($data, Response::HTTP_CREATED);          // 201
return \Bingo\Http\Response::json($data, Response::HTTP_NO_CONTENT);       // 204
return \Bingo\Http\Response::json($data, Response::HTTP_UNPROCESSABLE_ENTITY); // 422
```

---

## Server-Sent Events

```php
use Bingo\Http\Response;
use Bingo\Http\Sse\StreamedEvent;

return Response::eventStream(function (): \Generator {
    yield new StreamedEvent('message', ['text' => 'Hello']);
    yield new StreamedEvent('done', ['status' => 'complete']);
});
```

See [Server-Sent Events](server-sent-events.md) for full documentation.

---

## Raw Chunked Streaming

For chunked responses without SSE framing:

```php
return Response::stream(function (): void {
    echo "chunk one\n";
    flush();
    sleep(1);
    echo "chunk two\n";
    flush();
}, 200, ['Content-Type' => 'text/plain']);
```

Signature:

```php
Response::stream(callable $callback, int $status = 200, array $headers = []): StreamedResponse
```

The callback receives no arguments; write to output directly and call `flush()` after each chunk.

---

## Setting Headers

```php
$response = Response::json($data);
$response->headers->set('X-Custom-Header', 'value');
return $response;
```

Or declare headers declaratively with `#[Header]` on the controller method (see [Routing](routing.md#header)).
