# Auto-Discovery

Bingo discovers controllers and commands automatically.

Controllers are scanned from `app/Http/Controllers`, and commands are scanned from `app/Console/Commands`.

## What is discovered

- Controllers marked with `#[ApiController]`
- Route attributes such as `#[Get]`, `#[Post]`, and `#[Throttle]`
- Middleware attributes applied at class or method level
- Console commands extending Symfony Console command classes

## Discovery cache

Discovered metadata is written to `storage/framework/discovery.php`.

## Development mode

In development, Bingo rebuilds the cache automatically when the application detects file changes.

## Production mode

In production, the cache must be generated before deployment.

```bash
php bin/bingo discovery:generate
```

If the cache is missing in production, the application fails fast with a clear error.

## Cache commands

```bash
php bin/bingo discovery:generate
php bin/bingo discovery:clear
php bin/bingo show:routes
```

## Operational notes

- The cache file should be ignored by Git.
- Regenerate discovery after adding, removing, or renaming controllers and commands.
- Use `show:routes` to verify what is currently registered.
