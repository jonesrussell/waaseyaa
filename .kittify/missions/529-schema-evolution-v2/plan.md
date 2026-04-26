# Plan: #529 — Phased implementation (after design lock)

Design checkpoint is **`spec.md`** in this folder; child **#522** may absorb parts of that spec into `docs/specs/` as the authoritative contract.

## Phase 1 — SchemaDiff data model (empty engine)

- Readonly / sealed operation types; composite container; serialization for tests.
- No DB connection in the model layer.

## Phase 2 — MigrationInterface v2

- Interface + readonly migration value holding `SchemaDiff` (or op list).
- Document coexistence with legacy `Migration` in `Migrator`.

## Phase 3 — Diff → SQL compiler (SQLite first)

- Compiler seam: `SchemaDiff` + platform → ordered SQL statements or DBAL `Schema` diff.
- Align column specs with `docs/specs/field/column-derivation.md` and `SqlSchemaHandler` where possible (single source of truth goal).

## Phase 4 — Ledger + checksums

- Extend `MigrationRepository` / table schema (migration + ADR for backfill of existing rows).
- Record checksum on apply; reject mismatch on replay where policy says so.

## Phase 5 — dry-run + verify

- CLI flags; structured output; operator-safe messages (no raw paths in prod verify).

## Phase 6 — Composer manifest integration

- Support ordered `extra.waaseyaa.migrations` array (namespaces and/or paths); deprecate directory-only discovery with timeline.

## Phase 7 — Tests

- Unit: diff algebra, compiler golden files.
- Integration: kernel + sqlite; regression for #518 scenarios.
