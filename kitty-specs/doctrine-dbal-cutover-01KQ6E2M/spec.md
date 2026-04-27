# Doctrine DBAL Cutover — Specification (STUB)

**Status**: Stub. Full spec via `/spec-kitty.specify` when ready to plan. Largest mission of the attribute-track follow-ons.
**Coordinates with**: `#529-schema-evolution-v2`, `#1286-package-migrations` (Track 4 — Schema evolution).

---

## Scope statement

Replace `waaseyaa/database-legacy` (a Drupal-DBAL wrapper that exists "until Doctrine migration") with native **Doctrine DBAL** as the framework's database abstraction layer. Eliminates the framework's single largest piece of explicitly-named legacy debt. Apps and packages stop touching Drupal-shaped query builders and connection objects; framework storage drivers (`SqlEntityStorage`, `SqlStorageDriver`) are rewritten on top of Doctrine DBAL primitives.

## In-scope sketch

- Add `doctrine/dbal` direct dependency.
- Introduce a `Waaseyaa\Database\Doctrine\Connection` (or replace existing `DatabaseInterface` implementation) backed by Doctrine DBAL.
- Rewrite `SqlStorageDriver` query construction to use Doctrine's QueryBuilder (or Doctrine's Schema/Connection APIs directly where the query is trivial).
- Migrate every consumer of `database-legacy`:
  - `waaseyaa/entity-storage` storage drivers
  - `waaseyaa/foundation` rate limiter and other DB-touching utilities
  - Any app code that imports `Waaseyaa\Database\…` from the legacy path
- Coordinate column-spec derivation with `#1305` work (schema evolution `deriveColumnSpec()`).
- Coordinate migration-runner direction with `#529` (SchemaDiff) and `#1286` (package migration convention) — Doctrine's Schema API is a natural foundation for SchemaDiff.
- Remove `waaseyaa/database-legacy` from the monorepo and from the `waaseyaa/full` metapackage's deps.

## Out-of-scope sketch

- ORM-level features (Doctrine ORM proper). Stick to DBAL.
- Replacing the entity layer with Doctrine entities. The entity system stays Waaseyaa-native; only the underlying connection / SQL execution / schema-introspection layers move to Doctrine DBAL.
- DBAL upgrades or transactional changes that aren't required by the cutover itself.

## Open design questions

- Connection lifecycle / pooling: Doctrine DBAL has its own conventions; how does that fit Waaseyaa's request-scoped vs long-lived service model?
- Type registration: Doctrine's `Type` class maps to a different surface than `deriveColumnSpec`. Decide where the canonical PHP-type → SQL-type mapping lives.
- Test fixtures: SQLite-backed integration tests should continue to work without change. Verify Doctrine DBAL's SQLite driver behaves identically (especially around WAL, foreign keys, and CASCADE).
- Migration data continuity: existing `waaseyaa_migrations` ledger schema must keep working, or be migrated as part of the cutover.

## Coordination

- **#529 (SchemaDiff)**: this mission's Doctrine Schema API access is a natural input to the SchemaDiff engine. Sequence: probably land Doctrine-DBAL connection first, then refactor SchemaDiff on top of it.
- **#1286 (Package migrations)**: convention work proceeds independently of the underlying driver; cutover should not invalidate that mission's design.
- **`waaseyaa/database-legacy`**: full removal happens at the end of this mission's last work package.

## Risk profile

- **High blast radius.** Every package that touches the database is affected. Best executed as multiple small WPs, each landing one consumer at a time on a Doctrine-flavored adapter, with the legacy package gradually shedding consumers before deletion.
- Strongly benefits from a dedicated test pass against PostgreSQL and MySQL backends (not just SQLite) before merge — Doctrine DBAL behavior diverges from Drupal DBAL most visibly on those.
