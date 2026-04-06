# Helpers

Bingo provides a small set of global helper functions available throughout the application. They are defined in `core/helpers.php` and loaded automatically via Composer.

---

## `base_path()`

Returns the absolute path to the application root (the directory containing `composer.json`). An optional sub-path is appended when provided.

```php
base_path();                          // /var/www/my-app
base_path('storage/logs');            // /var/www/my-app/storage/logs
base_path('app/Http/Controllers');    // /var/www/my-app/app/Http/Controllers
```

---

## `database_path()`

Returns the absolute path to the `database/` directory. Equivalent to `base_path('database')`.

```php
database_path();                      // /var/www/my-app/database
database_path('migrations');          // /var/www/my-app/database/migrations
database_path('database.sqlite');     // /var/www/my-app/database/database.sqlite
```

---

## `env()`

Retrieves an environment variable. Returns `$default` when the variable is not set or is an empty string.

```php
env('APP_NAME');                   // 'Bingo' (from $_ENV or getenv)
env('MISSING_VAR', 'fallback');   // 'fallback'
env('APP_DEBUG');                  // true  (string 'true' → bool true)
env('APP_DEBUG');                  // false (string 'false' → bool false)
env('DB_PASSWORD', null);          // null  (string 'null' → PHP null)
```

### Type Coercion

`env()` converts common string representations to their PHP equivalents:

| String value | PHP value |
|---|---|
| `"true"` or `"(true)"` | `true` |
| `"false"` or `"(false)"` | `false` |
| `"null"` or `"(null)"` | `null` |
| anything else | the original string |

This mirrors the behaviour of Laravel's `env()` helper.

> **Note:** Prefer injecting typed config objects (`AppConfig`, `LogConfig`, etc.) over calling `env()` directly in business logic. Direct `env()` calls are appropriate in `config/` classes and `bootstrap/app.php`.
