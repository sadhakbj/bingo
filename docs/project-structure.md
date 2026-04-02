# Project Structure

A typical Bingo application uses the following layout:

```text
app/
  Console/
    Commands/         # Custom CLI commands
  Exceptions/         # Application exception handler
  DTOs/               # Input and output transfer objects
  Http/
    Controllers/      # Route controllers
    Middleware/       # Application middleware
  Models/             # Eloquent models
  Services/            # Business logic

bin/
  bingo               # CLI entry point

bootstrap/
  app.php             # HTTP application bootstrap
  console.php         # Console bootstrap

config/
  AppConfig.php       # Typed application config
  DbConfig.php        # Database configuration

core/
  Bingo/              # Framework code
  helpers.php         # Shared helpers

database/
  migrations/        # Migration files

public/
  index.php          # Web entry point

storage/
  framework/         # Generated discovery cache
  logs/              # Application logs
  rate-limit/        # File-based fallback store
```

Application code should live under `app/`. Framework code lives under `core/`.
