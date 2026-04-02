# Logging

Bingo ships with a PSR-3 compatible logger built on Monolog.

## Output formats

Logging supports two formats:

- `text` for human-readable output
- `json` for log shippers and observability platforms

## Configuration

```env
LOG_FORMAT=text
LOG_CHANNEL=stack
LOG_LEVEL=debug
LOG_STDERR_LEVEL=error
LOG_PATH=storage/logs/bingo.log
```

## Automatic logging

Bingo logs:

- Exceptions
- Framework-level HTTP errors
- Application log messages written through `LoggerInterface`

## Injecting the logger

```php
class UserService
{
    public function __construct(private readonly LoggerInterface $logger) {}
}
```

## Custom logger

You can override the logger in `bootstrap/app.php` by binding your own `LoggerInterface` instance.
