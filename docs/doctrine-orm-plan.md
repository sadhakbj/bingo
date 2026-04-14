# Doctrine ORM Migration Plan (Design Proposal)

This document outlines how Bingo would look if the framework used Doctrine ORM instead of Eloquent, while preserving the current API-first developer experience.

---

## Goals

- Keep Bingo’s current request lifecycle, routing, DTO validation, and DI model unchanged.
- Introduce Doctrine ORM as the persistence layer with clear extension points.
- Minimize breaking changes for application code by migrating via repositories/services.
- Support a phased rollout where Eloquent can coexist temporarily.

---

## Current State

- Persistence bootstraps through `core/Bingo/Database/Database.php` using Eloquent Capsule.
- Models in `app/Models/` extend `Illuminate\Database\Eloquent\Model`.
- Services and repositories commonly depend on Eloquent query APIs and collections.
- Migrations are currently Eloquent/Illuminate schema based.

---

## Target State (How Bingo Would Look)

### Runtime Components

- `core/Bingo/Database/Doctrine/EntityManagerFactory.php`  
  Builds and configures Doctrine `EntityManager` from typed Bingo DB config.
- `core/Bingo/Database/Doctrine/DoctrineManagerRegistry.php`  
  Optional registry wrapper for multi-connection use cases.
- `core/Bingo/Database/Doctrine/Types/`  
  Custom DBAL types when needed (UUID, JSON variants, etc.).

### Application Layer

- `app/Entities/` holds Doctrine entities (attributes mapping).
- `app/Repositories/` contains repository classes depending on Doctrine `EntityManagerInterface`.
- Services keep orchestration logic and return DTOs/resources as today.

### Container Bindings

- Bind `Doctrine\ORM\EntityManagerInterface` as a singleton in bootstrap/provider.
- Optionally bind `Doctrine\Persistence\ManagerRegistry` for advanced integrations.

### CLI

- Add Doctrine-focused commands (wrapper commands in `bin/bingo`) for:
  - migration diff/generate
  - migration migrate
  - schema validate

---

## Proposed Composer Dependency Changes

- Add:
  - `doctrine/orm`
  - `doctrine/migrations`
  - `doctrine/dbal`
  - `symfony/cache` (recommended for metadata/query caches)
- Keep `illuminate/database` during transition; remove after full migration.

---

## Compatibility Strategy

### Phase 1 — Infrastructure

- Introduce Doctrine bootstrapping and container bindings.
- Keep existing Eloquent path as default.
- Add configuration toggles (example: `DB_ORM_DRIVER=eloquent|doctrine`).

### Phase 2 — Dual Support

- Add repository contracts in `core` or `app` boundaries.
- Migrate selected modules from Eloquent models to Doctrine entities/repositories.
- Keep controllers and DTO interfaces stable.

### Phase 3 — Default Switch

- Make Doctrine default in new project templates/generators.
- Keep Eloquent compatibility mode for one major release cycle.

### Phase 4 — Cleanup

- Remove Eloquent-specific internals and docs once deprecation window closes.

---

## Configuration Model (Proposed)

- Reuse existing typed DB configuration classes where possible.
- Add Doctrine-specific settings:
  - proxy dir / auto-generate proxies
  - metadata paths
  - naming strategy
  - query/result cache configuration
  - migration namespace/path

---

## Example Directory Shape (Doctrine-first)

```text
app/
  Entities/
    User.php
    Post.php
  Repositories/
    UserRepository.php
    PostRepository.php
core/Bingo/Database/
  Doctrine/
    EntityManagerFactory.php
    DoctrineManagerRegistry.php
    Types/
database/
  migrations/
```

---

## Risks and Trade-offs

- Higher initial complexity (unit of work, entity lifecycle, mapping rules).
- Existing Eloquent-style APIs in userland code require refactoring.
- Migration tooling and schema diffing behavior differs from current workflow.
- Performance tuning needs explicit cache setup in production.

---

## Validation Checklist Before Full Adoption

- Doctrine bootstraps in HTTP and CLI contexts.
- Existing sample app behavior remains unchanged through repository abstraction.
- Migration command workflow is documented and tested.
- Production cache strategy (metadata/query/result) is configured.
- Upgrade guide from Eloquent to Doctrine is published.
