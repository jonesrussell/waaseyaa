# Tasks — Schema Evolution v2.0 (#529)

**Mission**: `529-schema-evolution-v2`
**Mission ID**: `01KQN41MQD3Y6PG0PES8XX166F`
**GitHub epic**: [waaseyaa/framework#529](https://github.com/waaseyaa/framework/issues/529)
**Branch contract**: `main` → `main` (matches target).
**Work package count**: 11 — average ≈6.2 subtasks/WP.
**Ratification status**: §15 Q1–Q9 locked 2026-05-02.

---

## Subtask Index

| ID | Description | WP | Parallel |
|---|---|---|---|
| T001 | Author SchemaDiff design spec (`docs/specs/schema-evolution-v2.md` §1–§14) | WP01 |  |
| T002 | §15 propose resolutions for §14 open questions | WP01 |  |
| T003 | §15 ratify resolutions Q1–Q9 (locked 2026-05-02) | WP01 |  |
| T004 | `SchemaDiffOp` readonly base + `OpKind` enum (foundation) | WP02 |  |
| T005 | Column ops (`AddColumn`, `AlterColumn`, `DropColumn`) + `ColumnSpec` value type | WP02 |  |
| T006 | Index ops (`AddIndex`, `DropIndex`) with composite-column support | WP02 |  |
| T007 | Rename ops (`RenameColumn`, `RenameTable`) — never inferred from drop+add | WP02 |  |
| T008 | Foreign-key ops (`AddForeignKey`, `DropForeignKey`) + `ForeignKeySpec` | WP02 |  |
| T009 | `CompositeDiff` + canonical JSON serialization + SHA-256 hash (Q2) | WP02 |  |
| T010 | Unit tests for diff algebra (immutability, equality, golden hashes) | WP02 |  |
| T011 | `MigrationInterfaceV2` readonly contract (no up/down; payload is SchemaDiff) | WP03 |  |
| T012 | `MigrationPlan` readonly DTO wrapping metadata + root `CompositeDiff` | WP03 |  |
| T013 | Empty plan = `CompositeDiff::empty()` per Q3 | WP03 |  |
| T014 | `MigrationId` format + uniqueness (`{vendor}/{package}:v2:{kebab}`) per Q1 | WP03 |  |
| T015 | Dependency metadata semantics (extends `$after`, locks data shape) | WP03 |  |
| T016 | Unit tests for plan structure + metadata validation | WP03 |  |
| T017 | `SqliteCompiler` entry + `SqliteCapabilities` capability declaration | WP04 |  |
| T018 | `AddColumn` → SQLite SQL generator (additive only) | WP04 |  |
| T019 | `AddIndex` → SQLite SQL generator (composite + unique) | WP04 |  |
| T020 | `RenameColumn` → `ALTER TABLE … RENAME COLUMN` (SQLite ≥ 3.25 capability gate) | WP04 |  |
| T021 | `RenameTable` → `ALTER TABLE … RENAME TO` generator | WP04 |  |
| T022 | `CompiledMigrationPlan` immutable output type with `diff_hash()` (Q2) | WP04 |  |
| T023 | Determinism golden tests (byte-identical step output across runs) | WP04 |  |
| T024 | Reject unknown `OpKind` with structured `UNKNOWN_OP_KIND` | WP05 |  |
| T025 | Reject `AlterColumn` on SQLite v1 with code `ALTER_COLUMN_UNSUPPORTED_SQLITE_V1` (Q5) | WP05 |  |
| T026 | Block `DropColumn` / `DropIndex` without `PlanPolicy(allowDestructive: true)` | WP05 |  |
| T027 | Reject FK ops on SQLite with code `FOREIGN_KEY_UNSUPPORTED_SQLITE_V1` (Q6) | WP05 |  |
| T028 | `OrderingValidator` detects illegal op ordering (`ILLEGAL_OP_ORDER`) | WP05 |  |
| T029 | SQLite capability matrix codified + companion ADR | WP05 |  |
| T030 | Unit tests for validation rules (every gate, every code) | WP05 |  |
| T031 | Single DAG ordering algorithm (Kahn + tie-break `(package, id)`) per Q4 | WP06 |  |
| T032 | `Migrator::run()` dispatch path: legacy via `up()`, v2 via compiler + executor | WP06 |  |
| T033 | Cross-kind dependency edges (legacy ↔ v2) + `UNKNOWN_DEPENDENCY` | WP06 |  |
| T034 | Cycle detection at boot/migrate with `MIGRATION_CYCLE` listing the cycle | WP06 |  |
| T035 | Batch boundary semantics (one batch per apply, mixed kinds) | WP06 |  |
| T036 | Unit tests for ordering + cycle detection + cross-kind edges | WP06 |  |
| T037 | Integration test: kernel + sqlite mixed legacy/v2 chain in deterministic order | WP06 |  |
| T038 | `EntityLevelDiff` readonly wrapper (entity_type_id + composite) | WP07 |  |
| T039 | `BundleLevelDiff` for single-bundle subtable scope | WP07 |  |
| T040 | `EntityDiffFactory` (EntityType + snapshot → EntityLevelDiff) | WP07 |  |
| T041 | Use `SqlSchemaHandler::deriveColumnSpec()` as single column-spec source | WP07 |  |
| T042 | Subtable handling per `bundle-scoped-storage.md` ({base}__{bundle}) | WP07 |  |
| T043 | Unit tests for factory (core fields, bundle fields, FieldStorage::Data exclusion) | WP07 |  |
| T044 | Additive-case integration fixtures (AddColumn, AddIndex baseline) | WP08 |  |
| T045 | Rename-like fixtures (explicit `RenameColumn`; verify drop+add never coalesces) | WP08 |  |
| T046 | Destructive fixtures (policy gates, FK rejection on SQLite) | WP08 |  |
| T047 | Bundle subtable diff fixtures ({base}__{bundle} scope) | WP08 |  |
| T048 | `FieldStorage::Data` scenarios (no column ops; round-trip via `_data`) | WP08 |  |
| T049 | Idempotency tests (same plan twice = no-op + checksum guard when WP09 lands) | WP08 |  |
| T050 | Schema migration adding `checksum` + `diff_hash` columns to `waaseyaa_migrations` | WP09 |  |
| T051 | SHA-256 checksum write on successful apply (post-commit) | WP09 |  |
| T052 | Replay rejection: `CHECKSUM_MISMATCH` in production, log+continue in dev | WP09 |  |
| T053 | Backfill ADR + optional `migrate:backfill-checksums` CLI script | WP09 |  |
| T054 | `MigrationRepository::verifyChecksum()` + `allWithChecksums()` hooks | WP09 |  |
| T055 | Unit + integration tests covering write, replay-guard, dev-vs-prod, verify | WP09 |  |
| T056 | `bin/waaseyaa migrate --dry-run` flag (Q8) — compiles + prints, no writes | WP10 |  |
| T057 | `bin/waaseyaa migrate --verify` flag — checksum-vs-source comparison | WP10 |  |
| T058 | Structured JSON output schema for both flags | WP10 |  |
| T059 | Production output sanitization (strip raw filesystem paths) | WP10 |  |
| T060 | Operator diagnostic codes integrated with existing `Diagnostic` conventions | WP10 |  |
| T061 | CLI tests + integration test for dry-run / verify | WP10 |  |
| T062 | `PackageManifest` widens `migrations` to `string\|list<string>` | WP11 |  |
| T063 | `MigrationLoader` parses ordered array entries (FQCN + path strings) | WP11 |  |
| T064 | Coexistence: string path supported indefinitely per Q9 | WP11 |  |
| T065 | Discovery rules ADR (`docs/adr/009-migration-manifest-discovery.md`) | WP11 |  |
| T066 | Validate array order preserved end-to-end | WP11 |  |
| T067 | CHANGELOG + workflow.md + infrastructure.md announce array preference | WP11 |  |
| T068 | Unit + integration tests for both manifest forms | WP11 |  |

68 subtasks across 11 work packages.

---

## Dependency Graph

```
WP01 (design — done)
   │
   ├─► WP02 (SchemaDiff atomic types) ─┬─► WP04 (SQLite compiler) ─► WP05 (validation gates)
   │                                    │
   │                                    └─► WP07 (EntityLevelDiff factory)
   │
   ├─► WP03 (MigrationPlan + Interface) ─┬─► WP06 (Migrator unified DAG)
   │                                      │
   │                                      ├─► WP07
   │                                      │
   │                                      ├─► WP09 (Ledger checksums) ─► WP10 (CLI)
   │                                      │
   │                                      └─► WP11 (Composer manifest array)
   │
   └─► (WP02–WP07 all merge into WP08 regression tests)
```

- **WP01** is the design ratification (already done 2026-05-02). All implementation WPs reference its locked Q1–Q9 decisions.
- **WP02 / WP03** are foundation atomics; they unblock everything downstream and have no internal dependency between them other than WP03 → WP02 (interface holds a CompositeDiff).
- **WP04 → WP05** is sequential: compiler core then validation gates layered on top.
- **WP06** waits on WP03, WP04, WP05 — the unified Migrator graph needs all three pieces.
- **WP07** waits on WP02 + WP03 — entity-storage factories consume both atomic types and the interface.
- **WP08** is the regression test fixture set; depends on every implementation WP (WP02–WP07).
- **WP09** depends on WP03 (interface) + WP06 (Migrator) — ledger writes happen during the v2 dispatch path.
- **WP10** waits on WP04 (compiler), WP06 (Migrator), WP09 (verify hooks).
- **WP11** depends only on WP03 (interface) — manifest discovery feeds the Loader independently of the Migrator.

---

## Work Package Details

### WP01 — Design ratification (#522)

**Status**: done (2026-05-02). 406-line subsystem spec at `docs/specs/schema-evolution-v2.md` and Q1–Q9 ratification block in this folder's `spec.md`.
**Subtasks**: T001 → T003.
**Prompt**: [`tasks/WP01-design-ratification.md`](./tasks/WP01-design-ratification.md)

### WP02 — SchemaDiff atomic types + CompositeDiff (foundation)

Land the pure data layer. Immutable readonly types for every supported op + `CompositeDiff` with canonical JSON + SHA-256.
**Subtasks**: T004 → T010.
**Prompt**: [`tasks/WP02-schemadiff-atomic-types.md`](./tasks/WP02-schemadiff-atomic-types.md)

### WP03 — MigrationPlan + MigrationInterfaceV2 (foundation)

Authoring contract: readonly value-object migrations carrying metadata + a CompositeDiff payload. No up/down. `MigrationId` format locked.
**Subtasks**: T011 → T016.
**Prompt**: [`tasks/WP03-migration-plan-and-interface.md`](./tasks/WP03-migration-plan-and-interface.md)

### WP04 — SQLite compiler core (additive ops only)

Compile `CompositeDiff` → `CompiledMigrationPlan` for SQLite, additive ops only. Determinism is the contract.
**Subtasks**: T017 → T023.
**Prompt**: [`tasks/WP04-sqlite-compiler-core.md`](./tasks/WP04-sqlite-compiler-core.md)

### WP05 — Compiler validation gates + capability matrix

Layer safety gates: reject unknown ops, AlterColumn on SQLite (Q5), FK on SQLite (Q6), destructive without policy flag, illegal ordering. Ship the capability matrix.
**Subtasks**: T024 → T030.
**Prompt**: [`tasks/WP05-compiler-validation-gates.md`](./tasks/WP05-compiler-validation-gates.md)

### WP06 — Migrator integration (legacy + v2 unified graph)

One topological order across legacy + v2 with deterministic tie-break (Q4). Dispatch path; cycle detection; cross-kind edges.
**Subtasks**: T031 → T037.
**Prompt**: [`tasks/WP06-migrator-unified-graph.md`](./tasks/WP06-migrator-unified-graph.md)

### WP07 — EntityLevelDiff factory in entity-storage

Entity-scoped diff producer. EntityType + FieldDefinitionRegistry → EntityLevelDiff. Bundle subtable scope.
**Subtasks**: T038 → T043.
**Prompt**: [`tasks/WP07-entity-level-diff-factory.md`](./tasks/WP07-entity-level-diff-factory.md)

### WP08 — Regression test fixtures (#518)

The test surface that closes #518. Additive, rename-like, destructive, bundle subtable, FieldStorage::Data, idempotency.
**Subtasks**: T044 → T049.
**Prompt**: [`tasks/WP08-regression-test-fixtures.md`](./tasks/WP08-regression-test-fixtures.md)

### WP09 — Ledger checksum + diff_hash extensions

Audit trail per Q1 / Q2: SHA-256 over canonical JSON. Replay rejection in production. Backfill ADR.
**Subtasks**: T050 → T055.
**Prompt**: [`tasks/WP09-ledger-checksum.md`](./tasks/WP09-ledger-checksum.md)

### WP10 — dry-run + verify CLI surface

Operator surface per Q8: `bin/waaseyaa migrate --dry-run` + `--verify`. Structured JSON. Production output sanitization.
**Subtasks**: T056 → T061.
**Prompt**: [`tasks/WP10-dry-run-verify-cli.md`](./tasks/WP10-dry-run-verify-cli.md)

### WP11 — Composer manifest array form (Phase 6)

Ordered-array support for `extra.waaseyaa.migrations` per Q9. String path stays supported indefinitely. Discovery rules ADR.
**Subtasks**: T062 → T068.
**Prompt**: [`tasks/WP11-composer-manifest-array-form.md`](./tasks/WP11-composer-manifest-array-form.md)

---

## MVP scope

**WP01 (done) + WP02 + WP03 + WP04 + WP06** is the architectural MVP — the framework's SchemaDiff atomic types, MigrationInterfaceV2, an SQLite additive compiler, and a unified Migrator that runs both kinds in one ordered graph. With those, a v2 migration can be authored, compiled, and applied with full determinism. WP05 (validation gates), WP07 (EntityLevelDiff factory), WP09 (ledger checksums), WP10 (CLI), WP11 (manifest) are the polish + production-safety layers. WP08 is the regression test gate that closes #518.

---

## Parallelization summary

After WP02 and WP03 land, the implementation tree fans out:

- **Lane A**: WP02 → WP04 → WP05 (sequential — compiler + validation)
- **Lane B**: WP03 → WP06 (sequential — interface then Migrator)
- **Lane C**: WP02 + WP03 → WP07 (entity-storage factory)
- **Lane D**: WP03 → WP11 (manifest array form, independent of Migrator)
- **Convergence**: WP06 → WP09 → WP10 (ledger then CLI)
- **Final lane**: WP08 (regression tests, depends on everything)

`spec-kitty agent mission finalize-tasks` will compute the actual lanes from the dependency frontmatter.
