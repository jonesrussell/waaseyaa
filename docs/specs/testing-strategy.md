# Testing Strategy (Framework)

## Goals

- **Fast feedback:** `packages/*/tests/Unit` run on every push (lefthook `phpunit` + CI).
- **Integration where contracts matter:** HTTP kernel, routing, entity storage, and multi-package flows use SQLite (`DBALDatabase::createSqlite()` or project fixtures) under `tests/Integration/` or package integration suites.
- **No network in unit tests:** Mock HTTP, mail, and external services; reserve real I/O for explicit integration cases.

## Layers

| Layer | Tooling | Scope |
|-------|---------|--------|
| Unit | PHPUnit 10.5, `#[Test]` attributes | Single class, deterministic collaborators |
| Integration | PHPUnit, in-memory SQLite | Kernel boot, migrations, route dispatch smoke |
| Admin SPA | Vitest (`packages/admin`) | Plugins, composables; stub `useRuntimeConfig` / `$fetch` |
| E2E | Playwright (`packages/admin`) | Requires Nuxt + PHP backend; run from main repo |

## Conventions

- Use `#[CoversClass]` / `#[CoversNothing]` per project rules.
- Prefer real value objects over heavy mocks for logs and DTOs.
- When adding packages, register `autoload-dev` PSR-4 namespaces in the **root** `composer.json` dev autoload map so CI discovers tests.

## CI / Hooks

- **Pre-push:** `lefthook` runs composer policy, drift detector (`tools/drift-detector.sh`), phpstan (on PHP changes), and the **Unit** test suite.
- **Full suite:** `./vendor/bin/phpunit` locally before release branches; worktrees may use narrower targets if documented in `CLAUDE.md`.
