# Project Structure

A Bingo application separates application code (`app/`) from framework code (`core/`). Configuration lives in `config/`, entry points in `bootstrap/` and `public/`, and generated artifacts in `storage/`.

---

## Directory Layout

```text
app/
├── Console/
│   └── Commands/           # Custom CLI commands (discovered automatically)
├── DTOs/                   # Input and output data transfer objects
│   └── User/
│       ├── CreateUserDTO.php
│       └── UserDTO.php
├── Exceptions/             # Application exception handler
│   └── Handler.php
├── Http/
│   ├── Controllers/        # API controllers (discovered automatically)
│   │   ├── HomeController.php
│   │   └── UsersController.php
│   ├── Middleware/         # Application middleware
│   │   └── AuthMiddleware.php
│   └── Requests/           # ValidatedRequest subclasses (optional)
├── Models/                 # Eloquent models
│   ├── Post.php
│   └── User.php
├── Providers/              # Service providers (#[ServiceProvider])
│   └── AppServiceProvider.php
├── Repositories/           # Repository interfaces and implementations
│   ├── IUserRepository.php
│   └── EloquentUserRepository.php
└── Services/               # Business logic
    └── UserService.php

bin/
└── bingo                   # CLI entry point

bootstrap/
├── app.php                 # HTTP application bootstrap (DI, middleware, exception handler)
└── console.php             # Console bootstrap (requires app.php, no run())

config/
├── AppConfig.php           # APP_* env vars → typed object
├── CorsConfig.php          # CORS_* env vars → typed object
├── DbConfig.php            # Database connection map
├── LogConfig.php           # LOG_* env vars → typed object
├── MySqlConfig.php         # MySQL driver config (extend to customise)
├── PgSqlConfig.php         # PostgreSQL driver config
├── RateLimitConfig.php     # RATE_LIMIT_* / REDIS_* env vars → typed object
└── SQLiteConfig.php        # SQLite driver config

core/
└── Bingo/                  # Framework source code (do not edit)

database/
├── migrations/             # Migration PHP files (run with db:migrate)
└── database.sqlite         # Default SQLite database (gitignored)

public/
└── index.php               # Web entry point — all HTTP requests start here

storage/
├── framework/
│   └── discovery.php       # Generated discovery cache (gitignored)
├── logs/
│   └── bingo.log           # Rotating application log
└── rate-limit/             # File-based rate-limit counters (dev only)

tests/
├── Unit/
│   ├── Bingo/              # Framework unit tests
│   └── App/                # Application unit tests
└── Stubs/                  # Test doubles for controllers and services

.env                        # Local environment variables (gitignored)
.env.example                # Template — commit this, not .env
composer.json
composer.lock
```

---

## Naming Conventions

| Component | Convention | Example |
|---|---|---|
| Controllers | `PascalCase` + `Controller` suffix | `UsersController` |
| Services | `PascalCase` + `Service` suffix | `UserService` |
| Repositories | Interface: `I` prefix; Implementation: `Eloquent` prefix | `IUserRepository`, `EloquentUserRepository` |
| DTOs | Prefixed with action for inputs, noun for outputs | `CreateUserDTO`, `UserDTO` |
| Middleware | `PascalCase` + `Middleware` suffix | `AuthMiddleware` |
| Commands | `PascalCase` + `Command` suffix | `SendDigestEmailCommand` |
| Exceptions | Descriptive name + `Exception` suffix | `PaymentDeclinedException` |
| Migrations | `YYYY_MM_DD_HHMMSS_description.php` | `2024_01_15_120000_create_users_table.php` |

---

## Namespaces

| Directory | Namespace |
|---|---|
| `app/` | `App\` |
| `core/Bingo/` | `Bingo\` |
| `config/` | `Config\` |
| `tests/` | `Tests\` |

---

## Important Files

| File | Role |
|---|---|
| `public/index.php` | Web front controller — do not edit |
| `bootstrap/app.php` | Register services, middleware, exception handler |
| `bootstrap/console.php` | Console kernel — `require`s `app.php` but does not call `run()` |
| `app/Exceptions/Handler.php` | Customise JSON error responses |
| `config/DbConfig.php` | Add or remove database connections |
| `storage/framework/discovery.php` | Generated cache — do not commit to Git |
