<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The Application boots automatically:
|   - Loads .env (optional — Docker/K8s inject vars directly)
|   - Builds typed AppConfig and DatabaseConfig from environment
|   - Boots Eloquent with all configured connections
|   - Wires DI container, router, and middleware pipeline
|
*/

require_once __DIR__ . '/../vendor/autoload.php';

use Bingo\Application;

$app = Application::create(dirname(__DIR__));

/*
|--------------------------------------------------------------------------
| VarDumper (dd / dump) under PHP built-in server
|--------------------------------------------------------------------------
|
| With Accept: application/json, Symfony VarDumper uses CliDumper → php://stdout.
| `php bin/bingo serve` redirects that child stdout to /dev/null, so dd() looked
| like "empty" responses. Prefer HtmlDumper (php://output) for web dumps here.
|
*/

if (PHP_SAPI === 'cli-server') {
    $_SERVER['VAR_DUMPER_FORMAT'] ??= 'html';
}

/*
|--------------------------------------------------------------------------
| Register Services (Dependency Injection)
|--------------------------------------------------------------------------
|
| Concrete classes with typed constructor deps are auto-resolved — no
| registration needed. Only register:
|   - Interface → concrete bindings
|   - Pre-built objects you want to share
|
| AppConfig and DatabaseConfig are pre-registered automatically.
|
*/

// $app->singleton(\App\Contracts\CacheInterface::class, \App\Services\RedisCache::class);

/*
|--------------------------------------------------------------------------
| Rate Limiting
|--------------------------------------------------------------------------
|
| The production middleware pipeline includes RateLimitMiddleware at
| 1 000 req/min per IP by default. Override it here if you need a
| different limit, window, or key strategy.
|
| Storage backend — automatically selected:
|   FileStore   — dev fallback when phpredis is not loaded (writes to storage/rate-limit/)
|   RedisStore  — used in production when the phpredis extension is present
|   Custom      — implement RateLimiterStore (3 methods) and bind in bootstrap/app.php
|
*/

// use Bingo\Http\Middleware\RateLimitMiddleware;
// use Bingo\RateLimit\Contracts\RateLimiterStore;
// use Bingo\RateLimit\Store\FileStore;
//
// // Tighter global limit
// $app->use(RateLimitMiddleware::perMinute(60));
//
// // Persist counters across restarts (single server)
// $app->instance(RateLimiterStore::class, new FileStore(base_path('storage/rate-limit')));

/*
|--------------------------------------------------------------------------
| Logging
|--------------------------------------------------------------------------
|
| Bingo boots a PSR-3 Monolog logger automatically:
|   - stderr  (ERROR+)  — picked up by Docker / k8s log shippers
|   - rotating file (DEBUG+) — stored at LOG_PATH
|
| Override the logger for custom channels or to wire OTel:
|
|   use Monolog\Logger;
|   use Monolog\Handler\StreamHandler;
|   use Monolog\Level;
|   $logger = new Logger('my-app', [new StreamHandler('php://stdout', Level::Debug)]);
|   $app->instance(\Psr\Log\LoggerInterface::class, $logger);
|
| OTel migration (Prometheus / Loki / Datadog):
|
|   composer require open-telemetry/opentelemetry-logger-monolog
|   // Then replace the logger instance above with an OTel-backed one.
|   // trace_id and span_id are already reserved in RequestContextProcessor.
|
*/

// $app->instance(\Psr\Log\LoggerInterface::class, $yourLogger);

/*
|--------------------------------------------------------------------------
| Exception handling (application layer — not in core)
|--------------------------------------------------------------------------
|
| The package default lives in Bingo\Exceptions\ExceptionHandler. To own the
| format (Problem+JSON, JSend, ApiResponse errors, etc.), register your app
| handler — see App\Exceptions\Handler (template) and README.
|
*/

$app->exceptionHandler(new \App\Exceptions\Handler($app->isDebug()));

/*
|--------------------------------------------------------------------------
| Auto-Discovery
|--------------------------------------------------------------------------
|
| Controllers, commands, and middleware are automatically discovered via
| PHP attributes. Just create controllers with #[ApiController] or route
| attributes (#[Get], #[Post], etc.) — no manual registration needed.
|
| In production, pre-build the cache for zero overhead:
|   php bin/bingo discovery:generate
|
| In development, the cache rebuilds automatically when files change.
|
*/

// No controller registration needed — discovery handles it automatically!
// Manual registration is still available if needed for edge cases:
// $app->controller(SomeSpecialController::class);

return $app;
