# Deployment

Bingo is designed to run as a stateless PHP service behind a web server or PHP-FPM. The steps below cover bare-metal, Docker, and Kubernetes deployments.

---

## Pre-Flight Checklist

Before deploying to any environment:

- [ ] `composer install --no-dev --optimize-autoloader` run successfully
- [ ] `php bin/bingo discovery:generate` has been run
- [ ] `storage/framework/discovery/` is present in the image / deployment artifact
- [ ] `APP_ENV=production` is set
- [ ] `APP_DEBUG=false` is set
- [ ] Database credentials are configured via environment variables
- [ ] The public web root points to `public/index.php`
- [ ] `storage/logs/` is writable
- [ ] The rate-limiting backend (Redis) is reachable if `RATE_LIMIT_DRIVER=redis`

---

## Traditional Deployment

```bash
# On the server
composer install --no-dev --optimize-autoloader
php bin/bingo discovery:generate
php bin/bingo db:migrate
```

Point your web server to `public/` as the document root. All requests that do not match a static file should be rewritten to `public/index.php`.

### nginx Example

```nginx
server {
    listen 80;
    server_name api.example.com;
    root /var/www/html/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass  unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include       fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Increase timeout for SSE connections
    location ~* /stream {
        fastcgi_read_timeout 3600;
        proxy_read_timeout   3600;
        try_files $uri /index.php?$query_string;
    }
}
```

---

## Docker

```dockerfile
FROM php:8.4-fpm-alpine

WORKDIR /var/www

# Install phpredis (optional, for production rate limiting)
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis

# Copy Composer files first to leverage layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy application source
COPY . .

# Run Composer scripts now that the full source is present
RUN composer dump-autoload --optimize --classmap-authoritative

# Pre-build the discovery cache during image build
RUN php bin/bingo discovery:generate

EXPOSE 9000
CMD ["php-fpm"]
```

### docker-compose.yml

```yaml
version: "3.9"

services:
  api:
    build: .
    ports:
      - "9000:9000"
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
      DB_CONNECTION: mysql
      DB_HOST: db
      DB_DATABASE: myapp
      DB_USERNAME: root
      DB_PASSWORD: secret
      REDIS_HOST: redis
      RATE_LIMIT_DRIVER: redis
    depends_on:
      - db
      - redis

  db:
    image: mysql:8
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: myapp
    volumes:
      - db_data:/var/lib/mysql

  redis:
    image: redis:7-alpine

volumes:
  db_data:
```

---

## Kubernetes

### Deployment Manifest

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: bingo-api
spec:
  replicas: 3
  selector:
    matchLabels:
      app: bingo-api
  template:
    metadata:
      labels:
        app: bingo-api
    spec:
      containers:
        - name: php-fpm
          image: your-registry/bingo-api:latest
          ports:
            - containerPort: 9000
          env:
            - name: APP_ENV
              value: production
            - name: APP_DEBUG
              value: "false"
            - name: DB_HOST
              valueFrom:
                secretKeyRef:
                  name: db-credentials
                  key: host
            - name: REDIS_HOST
              value: redis-service
            - name: RATE_LIMIT_DRIVER
              value: redis
          resources:
            requests:
              cpu: 100m
              memory: 128Mi
            limits:
              cpu: 500m
              memory: 256Mi
```

### Key Points for Kubernetes

- Bundle `vendor/` in the Docker image — do not use init containers to run `composer install` at startup.
- Run `php bin/bingo discovery:generate` **during the image build** (in the Dockerfile `RUN` step), not at container startup.
- Use a `Secret` for database credentials and Redis passwords.
- Use Redis (`RATE_LIMIT_DRIVER=redis`) for rate limiting when running multiple replicas. File-based limiting is per-pod.
- Set PHP-FPM pool workers based on available memory (`pm.max_children`).

---

## Environment Variables in Production

Never store secrets in source code or Docker images. Use:

- **Kubernetes Secrets** → mounted as env vars or files
- **AWS Secrets Manager / Parameter Store** → fetched at startup
- **HashiCorp Vault** → dynamic secrets injection
- **`.env` file** on managed servers (restrict file permissions: `chmod 600 .env`)

Bingo will read environment variables directly from the process, so Kubernetes-style
`env:` / `envFrom:` injection works without a `.env` file in the container image.

---

## Health Check

Add a lightweight health check endpoint:

```php
#[ApiController]
class HealthController
{
    #[Get('/health')]
    public function health(): Response
    {
        return Response::json(['status' => 'ok']);
    }
}
```

Configure your load balancer or k8s liveness probe to hit `GET /health`.

---

## Logging in Production

Set `LOG_FORMAT=json` and `LOG_STDERR_LEVEL=info` in production so log shippers (Fluentd, Loki, Datadog Agent) can parse structured entries from stderr:

```env
LOG_FORMAT=json
LOG_LEVEL=info
LOG_STDERR_LEVEL=info
```
