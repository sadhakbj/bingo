# Logging

Bingo ships with a PSR-3 compatible logger built on [Monolog v3](https://github.com/Seldaek/monolog). It writes to a rotating file and to stderr, making it compatible with Docker, Kubernetes, and cloud log shippers out of the box.

---

## Configuration

All logging options are controlled by environment variables (see [Configuration](configuration.md#logging-config-logconfig)):

```env
LOG_CHANNEL=stack
LOG_LEVEL=debug
LOG_STDERR_LEVEL=error
LOG_PATH=storage/logs/bingo.log
LOG_FORMAT=text
LOG_TIME_FORMAT=Y-m-d\TH:i:sP
```

| Variable | Default | Description |
|---|---|---|
| `LOG_CHANNEL` | `stack` | Channel name attached to every log record |
| `LOG_LEVEL` | `debug` | Minimum level for the file handler |
| `LOG_STDERR_LEVEL` | `error` | Minimum level for stderr (Docker / k8s) |
| `LOG_PATH` | `storage/logs/bingo.log` | Rotating file destination |
| `LOG_FORMAT` | `text` | `text` (slog-style) or `json` |
| `LOG_TIME_FORMAT` | `Y-m-d\TH:i:sP` | PHP date format for timestamps |

---

## Output Formats

### `text` (default)

Go slog-style `key=value` pairs, suitable for human reading in a terminal:

```
time=2024-01-15T10:30:00+00:00 level=INFO msg="Request received" method=GET path=/api/users request_id=abc123
```

### `json`

Structured JSON, suitable for log shippers (Loki, Datadog, Elasticsearch):

```json
{
  "channel": "stack",
  "level_name": "INFO",
  "message": "Request received",
  "context": { "method": "GET", "path": "/api/users" },
  "extra": { "request_id": "abc123" },
  "datetime": "2024-01-15T10:30:00+00:00"
}
```

Switch to JSON in production:

```env
LOG_FORMAT=json
LOG_LEVEL=info
```

---

## What Bingo Logs Automatically

- Unhandled exceptions (including HTTP exceptions)
- Framework-level errors
- Every request gets a unique `request_id` in log context (added by `RequestContextProcessor`)

---

## Injecting the Logger

Type-hint `Psr\Log\LoggerInterface` in any class:

```php
use Psr\Log\LoggerInterface;

class OrderService
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function processOrder(Order $order): void
    {
        $this->logger->info('Processing order', ['order_id' => $order->id]);

        try {
            // …
        } catch (\Exception $e) {
            $this->logger->error('Order processing failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

### PSR-3 Log Levels

```php
$this->logger->debug('Detailed debug info', $context);
$this->logger->info('Informational message', $context);
$this->logger->notice('Normal but significant', $context);
$this->logger->warning('Warning condition', $context);
$this->logger->error('Error condition', $context);
$this->logger->critical('Critical condition', $context);
$this->logger->alert('Needs immediate action', $context);
$this->logger->emergency('System is unusable', $context);
```

---

## Request Context

`RequestContextProcessor` automatically adds `request_id` to every log record so that all entries from a single HTTP request can be correlated:

```
time=… level=INFO msg="User created" request_id=f3a9c1d2 user_id=42
time=… level=INFO msg="Email queued" request_id=f3a9c1d2 email=user@example.com
```

---

## Custom Logger

Replace the logger entirely in `bootstrap/app.php`:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Psr\Log\LoggerInterface;

$logger = new Logger('my-app', [
    new StreamHandler('php://stdout', Level::Debug),
]);

$app->instance(LoggerInterface::class, $logger);
```

### OpenTelemetry Integration

```bash
composer require open-telemetry/opentelemetry-logger-monolog
```

Then replace the logger instance with an OTel-backed one. The `request_id` field in `RequestContextProcessor` reserves slots for `trace_id` and `span_id` when you add OTel instrumentation later.

---

## Log Rotation

The file handler rotates logs daily and keeps 14 days of history by default. Adjust log storage using `LOG_PATH` to point to a mounted volume in containerised environments.
