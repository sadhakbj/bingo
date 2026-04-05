# Rate Limiting

Bingo ships with a sliding-window rate limiter that works across processes and server instances. The primary backend is Redis; a file-based fallback is used automatically in development when phpredis is not available.

---

## How It Works

The sliding-window algorithm counts requests in a rolling time window, avoiding the burst spikes that fixed-window approaches allow at window boundaries. Each request is stored with a timestamp so expired entries are pruned continuously.

---

## Global Rate Limiting

The global middleware (`RateLimitMiddleware`) is active automatically in production. Configure it through environment variables:

```env
RATE_LIMIT_ENABLED=true
RATE_LIMIT_DRIVER=redis         # redis or file
RATE_LIMIT_REQUESTS=100         # max requests per window
RATE_LIMIT_WINDOW=60            # window length in seconds
```

By default the limit key is the client IP address. When the limit is exceeded Bingo returns `429 Too Many Requests` with standard headers.

---

## Storage Backends

### Redis (production)

Requires the `ext-redis` (phpredis) PHP extension. Set `RATE_LIMIT_DRIVER=redis` and configure the connection:

```env
RATE_LIMIT_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
```

Redis is the recommended backend for multi-process deployments (PHP-FPM, Kubernetes pods). The implementation uses atomic Lua scripts and is cluster-safe.

### File (development fallback)

When phpredis is not available or `RATE_LIMIT_DRIVER=file`, Bingo writes counters to `storage/rate-limit/`. This works for single-process development servers but is not suitable for production.

```env
RATE_LIMIT_DRIVER=file
```

### Custom Backend

Implement `Bingo\RateLimit\Contracts\RateLimiterStore` (three methods: `increment`, `count`, `reset`) and register it in `bootstrap/app.php`:

```php
use Bingo\RateLimit\Contracts\RateLimiterStore;

$app->instance(RateLimiterStore::class, new MyCustomStore());
```

---

## Per-Route Throttling

Use the `#[Throttle]` attribute to override the global limit for specific routes. The `per` argument is expressed in **seconds**.

### Controller-Level

Applies to all routes in the controller:

```php
use Bingo\Attributes\Route\Throttle;

#[ApiController('/api')]
#[Throttle(requests: 1000, per: 3600)]   // 1000 requests per hour
class PostsController
{
    #[Get('/posts')]
    public function index(): Response { /* … */ }
}
```

### Method-Level

Overrides the controller-level limit for a single route:

```php
#[ApiController('/api')]
#[Throttle(requests: 500, per: 3600)]
class PostsController
{
    #[Get('/posts')]
    public function index(): Response { /* … */ }

    #[Get('/posts/export')]
    #[Throttle(requests: 10, per: 60)]   // tighter limit for expensive endpoint
    public function export(): Response { /* … */ }
}
```

---

## Multiple Windows (Burst + Sustained)

Stack multiple `#[Throttle]` attributes on the same method to enforce both a burst limit and a sustained limit:

```php
#[Get('/search')]
#[Throttle(requests: 5,   per: 1)]    // max 5 per second (burst)
#[Throttle(requests: 200, per: 60)]   // max 200 per minute (sustained)
public function search(): Response { /* … */ }
```

Both limits must pass for the request to be allowed through.

---

## Programmatic Usage

Inject `Bingo\RateLimit\RateLimiter` to apply rate limiting from application code:

```php
use Bingo\RateLimit\RateLimiter;

class LoginController
{
    public function __construct(private readonly RateLimiter $limiter) {}

    #[Post('/login')]
    public function login(#[Body] LoginDTO $dto): Response
    {
        $result = $this->limiter->attempt(
            key:           'login:' . $dto->email,
            limit:         5,
            windowSeconds: 900,    // 15 minutes
        );

        if (!$result->allowed) {
            throw new TooManyRequestsException('Too many login attempts. Try again later.');
        }

        // proceed with authentication…
    }
}
```

`RateLimiter::attempt()` returns a `RateLimitResult` with these properties:

| Property | Type | Description |
|---|---|---|
| `allowed` | bool | Whether the request is within the limit |
| `remaining` | int | Requests remaining in the current window |
| `resetAt` | int | Unix timestamp when the window resets |

---

## Response Headers

When the global middleware rejects a request, or when `#[Throttle]` limits are hit, Bingo adds standard headers to the `429` response:

| Header | Description |
|---|---|
| `X-RateLimit-Limit` | Maximum requests allowed in the window |
| `X-RateLimit-Remaining` | Requests remaining in the current window |
| `X-RateLimit-Reset` | Unix timestamp when the window resets |
| `Retry-After` | Seconds until the client may retry |

---

## Customising the Global Middleware

Override the global rate limiter in `bootstrap/app.php`:

```php
use Bingo\Http\Middleware\RateLimitMiddleware;

// 60 requests per minute per IP
$app->use(RateLimitMiddleware::perMinute(60));
```
