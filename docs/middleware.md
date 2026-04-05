# Middleware

Middleware intercepts every request before it reaches the controller action. It is used for authentication, authorization, request logging, header injection, and other cross-cutting concerns.

---

## Global Middleware

Global middleware is registered in `bootstrap/app.php` with `$app->use()` and runs on **every** request:

```php
$app->use(App\Http\Middleware\AuthMiddleware::class);
$app->use(App\Http\Middleware\AuditLogMiddleware::class);
```

You can also pass a pre-constructed instance when a middleware requires configuration:

```php
$app->use(new App\Http\Middleware\ThrottleMiddleware(limit: 60));
```

Global middleware is executed in registration order.

---

## Per-Controller and Per-Route Middleware

Use `#[Middleware]` on a controller class to protect all routes in that controller:

```php
use Bingo\Attributes\Middleware;

#[ApiController('/admin')]
#[Middleware([AuthMiddleware::class])]
class AdminController
{
    #[Get('/dashboard')]
    public function dashboard(): Response { /* … */ }
}
```

Apply `#[Middleware]` on a method to protect only that route:

```php
#[ApiController('/posts')]
class PostsController
{
    #[Get('/')]
    public function index(): Response { /* … */ }    // no middleware

    #[Delete('/{id}')]
    #[Middleware([AuthMiddleware::class, AdminMiddleware::class])]
    public function destroy(#[Param('id')] int $id): Response { /* … */ }
}
```

Class-level middleware runs before method-level middleware.

---

## Writing Custom Middleware

Implement `Bingo\Contracts\MiddlewareInterface`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Bingo\Contracts\MiddlewareInterface;
use Bingo\Exceptions\Http\UnauthorizedException;
use Bingo\Http\Request;
use Bingo\Http\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $header = $request->headers->get('Authorization', '');

        if (!str_starts_with($header, 'Bearer ')) {
            throw new UnauthorizedException('Missing or invalid token');
        }

        $token = substr($header, 7);
        $request->attributes->set('auth_token', $token);

        return $next($request);
    }
}
```

Key rules:
- Always call `$next($request)` to pass control to the next middleware or the controller.
- Do not wrap `$next($request)` in a broad `catch` block unless you intend to swallow downstream exceptions.
- Throw a built-in HTTP exception (e.g. `UnauthorizedException`) for HTTP-level errors.

---

## Middleware That Modifies the Response

Middleware can inspect and modify the response returned by `$next`:

```php
public function handle(Request $request, callable $next): Response
{
    $response = $next($request);

    $response->headers->set('X-Powered-By', 'Bingo');

    return $response;
}
```

---

## Built-In Middleware

Bingo registers the following middleware automatically for every request:

| Middleware | What it does |
|---|---|
| `CorsMiddleware` | Handles CORS preflight (`OPTIONS`) and adds `Access-Control-*` headers. Configured via `CorsConfig` / `.env`. |
| `BodyParserMiddleware` | Decodes JSON, form-encoded, and multipart request bodies into `$request->request`. |
| `CompressionMiddleware` | Gzip-compresses responses for clients that send `Accept-Encoding: gzip`. Skipped for SSE streams. |
| `SecurityHeadersMiddleware` | Adds `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `X-XSS-Protection` to every response. |
| `RequestIdMiddleware` | Generates a unique `X-Request-ID` for each request and includes it in the response. |
| `RateLimitMiddleware` | Enforces the global sliding-window rate limit (production only by default). |

### CORS Configuration

Configure CORS behavior through environment variables (see [Configuration](configuration.md#cors-config-corsconfig)) or by updating `config/CorsConfig.php`:

```env
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
CORS_ALLOWED_METHODS=GET,POST,PUT,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,Authorization
CORS_SUPPORTS_CREDENTIALS=true
CORS_MAX_AGE=86400
```

For wildcard origins in development:

```env
CORS_ALLOWED_ORIGINS=*
```

### Security Headers

`SecurityHeadersMiddleware` sets these headers on every response:

| Header | Value |
|---|---|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `X-XSS-Protection` | `1; mode=block` |

### Request ID

`RequestIdMiddleware` adds `X-Request-ID` to every response. The ID is also available inside controllers and services:

```php
$requestId = $request->headers->get('X-Request-ID');
```

---

## Middleware Execution Order

For a request that hits a route with both controller-level and method-level middleware:

```
Global middleware (in registration order)
  → Controller-level #[Middleware]
  → Method-level #[Middleware]
  → Controller action
```
