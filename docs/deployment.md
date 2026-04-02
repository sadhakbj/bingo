# Deployment

Bingo is designed to run with pre-generated discovery metadata in production.

## Recommended deployment flow

```bash
composer install --no-dev --optimize-autoloader
php bin/bingo discovery:generate
```

## Production notes

- Do not rely on runtime discovery in production.
- Keep the discovery cache in sync with controller and command changes.
- Configure rate limiting and logging for the target environment.
- Store application secrets in environment variables or your deployment platform's secret store.

## Suggested checklist

- Verify the discovery cache exists.
- Confirm database credentials are set.
- Confirm the public web root points to `public/index.php`.
- Confirm logs are writable.
- Confirm the rate-limiting backend is available.
