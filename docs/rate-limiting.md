# Rate Limiting

Bingo includes a sliding-window rate limiter.

It can be used as middleware, through the `#[Throttle]` attribute, or directly from application code.

## Global rate limiting

The global middleware is active in production and protects the application by default.

```env
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_REQUESTS=1000
RATE_LIMIT_WINDOW=60
```

## Storage

Redis is the primary store. When the phpredis extension is not available, Bingo falls back to a file-based store for local development.

## Per-route throttling

```php
#[ApiController('/api')]
#[Throttle(requests: 1000, per: 3600)]
class PostsController
{
    #[Get('/posts')]
    public function index(): Response {}

    #[Get('/posts/export')]
    #[Throttle(requests: 10, per: 60)]
    public function export(): Response {}
}
```

The `per` argument is expressed in seconds.

## Multiple windows

You can stack multiple `#[Throttle]` attributes on a single route to enforce both burst and sustained limits.

```php
#[Get('/search')]
#[Throttle(requests: 5, per: 1)]
#[Throttle(requests: 100, per: 60)]
public function search(): Response {}
```

## Direct use

```php
$result = $limiter->attempt('login:' . $email, limit: 5, windowSeconds: 900);
```

## Response headers

Rate-limited responses include standard headers such as:

- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `X-RateLimit-Reset`
- `Retry-After`

## Algorithm

Bingo uses a sliding-window counter to avoid the burst edge cases of a fixed window.
