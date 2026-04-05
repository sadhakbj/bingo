# Auto-Discovery

Bingo automatically finds controllers and CLI commands from your `app/` directory without any manual registration. Discovery results are cached for production performance.

---

## What Is Discovered

| Component | Location | Trigger |
|---|---|---|
| API controllers | `app/Http/Controllers/` | `#[ApiController]` attribute |
| Route methods | on controllers | `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]`, `#[Head]`, `#[Options]`, `#[Route]` |
| Per-route middleware | on controllers/methods | `#[Middleware]` attribute |
| Per-route throttle | on controllers/methods | `#[Throttle]` attribute |
| Response metadata | on controllers/methods | `#[HttpCode]`, `#[Header]` |
| Console commands | `app/Console/Commands/` | extends Symfony `Command` |
| Interface bindings | anywhere in `app/` | `#[Bind]` on interface |
| Service providers | anywhere in `app/` | `#[ServiceProvider]` on class |

---

## Discovery Cache

Discovered metadata is serialised to `storage/framework/discovery.php`. This file is loaded on every request.

### Development Mode

In development (`APP_ENV=development`), Bingo compares file modification times against the cache and rebuilds automatically when any source file changes. There is no action required after adding, renaming, or removing a controller.

### Production Mode

In production, the cache must be built before deployment and must not be stale:

```bash
php bin/bingo discovery:generate
```

If the cache file is missing in production, the application throws a `RuntimeException` immediately on boot. Pre-building the cache during your CI/CD pipeline or Docker image build is strongly recommended.

---

## CLI Commands

```bash
# Build or rebuild the discovery cache
php bin/bingo discovery:generate

# Delete the discovery cache (forces a rebuild)
php bin/bingo discovery:clear

# Display all registered routes with methods, paths, and middleware
php bin/bingo show:routes
```

---

## Registering Controllers Manually

Discovery handles all standard cases. Manual registration is available for edge cases (e.g. dynamically constructed controllers):

```php
// bootstrap/app.php
$app->controller(SomeSpecialController::class);
```

---

## Git and the Cache

Add the discovery cache to `.gitignore`:

```gitignore
storage/framework/discovery.php
```

Rebuild it as part of your deployment pipeline rather than committing it to source control.

---

## Under the Hood

Discovery is orchestrated by `Bingo\Discovery\DiscoveryManager` using four discoverers:

| Discoverer | Finds |
|---|---|
| `ControllerDiscoverer` | `#[ApiController]` classes and their route attributes |
| `CommandDiscoverer` | Symfony `Command` subclasses |
| `BindingDiscoverer` | `#[Bind]` interface-to-concrete mappings |
| `ProviderDiscoverer` | `#[ServiceProvider]` classes |

Each discoverer scans the configured directories using PHP reflection and writes structured metadata to the cache file.
