# Middleware

Middleware runs before the controller action is executed.

It is useful for authentication, authorization, request logging, CORS, and other request-level behavior.

## Global middleware

Global middleware is registered in `bootstrap/app.php` and runs on every request.

```php
$app->use(App\Http\Middleware\AuthMiddleware::class)
    ->use(App\Http\Middleware\LogMiddleware::class);
```

You can also register an already constructed instance when a middleware needs configuration.

## Controller and route middleware

Use `#[Middleware]` on a controller class to apply middleware to all routes in that controller.

Apply it to a single method when only one route needs protection.

```php
#[ApiController('/admin')]
#[Middleware([AuthMiddleware::class])]
class AdminController
{
    #[Get('/dashboard')]
    public function dashboard(): Response {}

    #[Get('/logs')]
    #[Middleware([AuditLogMiddleware::class])]
    public function logs(): Response {}
}
```

## Writing custom middleware

Custom middleware implements `Bingo\Contracts\MiddlewareInterface`.

```php
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->headers->get('Authorization');

        if (!$token || !str_starts_with($token, 'Bearer ')) {
            throw new UnauthorizedException('Missing or invalid token');
        }

        $request->attributes->set('auth_token', substr($token, 7));

        return $next($request);
    }
}
```

Always call `$next($request)` outside broad exception handling so downstream middleware and controllers can surface their own exceptions.

## Built-in middleware

Bingo registers the following middleware automatically:

- `CorsMiddleware`
- `BodyParserMiddleware`
- `CompressionMiddleware`
- `SecurityHeadersMiddleware`
- `RequestIdMiddleware`
- `RateLimitMiddleware`

## Notes

- CORS behavior is restricted by environment.
- The body parser supports JSON, form-encoded, and multipart payloads.
- Compression is skipped for streamed responses and SSE.
- The request ID middleware adds `X-Request-ID` to each response.
