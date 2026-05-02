# Mission spec: #529 — Schema evolution v2.0 (SchemaDiff pivot)

**GitHub epic:** [waaseyaa/framework#529](https://github.com/waaseyaa/framework/issues/529)  
**Milestone:** Track 4 — Schema evolution  
**Child issues (execution spine):** [#522](https://github.com/waaseyaa/framework/issues/522) (design), [#521](https://github.com/waaseyaa/framework/issues/521) (implement), [#518](https://github.com/waaseyaa/framework/issues/518) (test)  
**Unblocked by:** [#1305](https://github.com/waaseyaa/framework/issues/1305) (column derivation), [#1286](https://github.com/waaseyaa/framework/issues/1286) (package migrations convention)

---

## Ratified Resolutions (Q1–Q9) — 2026-05-02

**Status: RATIFIED.** The nine §14 open questions in [`docs/specs/schema-evolution-v2.md`](../../docs/specs/schema-evolution-v2.md) are **locked** as of 2026-05-02. Each resolution is the binding architectural decision for Phase 2+ implementation. Further changes require an ADR and an explicit overturn note in the subsystem spec; resolutions may not be relitigated implicitly during WP execution.

| ID | Topic | Ratified resolution |
|----|-------|---------------------|
| **Q1** | `migration_id` vs current `migration` column | **B** — `migration` remains the sole canonical ledger key; `checksum` and `diff_hash` (nullable) extend the row. |
| **Q2** | Checksum algorithm and canonical serialization | **A** — SHA-256 over canonical JSON (sorted keys, UTF-8) for both `checksum` and `diff_hash`. |
| **Q3** | `MigrationPlan` vs `CompositeDiff` (empty plan) | **B** — single composite root; empty plan = `CompositeDiff([])`. |
| **Q4** | Merge algorithm (legacy + v2 in one graph) | **B** — single DAG; topological sort; tie-break `(package ASC, migration ASC)`. |
| **Q5** | SQLite `AlterColumn` support (v1 compiler) | **B** — reject `AlterColumn` at compile time on SQLite in v1 with a stable diagnostic code. |
| **Q6** | Foreign keys on SQLite (v1 compiler) | **B** — reject `AddForeignKey` / `DropForeignKey` on SQLite in v1 (`FOREIGN_KEY_UNSUPPORTED_SQLITE_V1`). |
| **Q7** | Where `EntityLevelDiff` is produced | **B** — atomic value types in `waaseyaa/foundation`; entity-scoped factories in `waaseyaa/entity-storage`. |
| **Q8** | CLI surface (`--dry-run` / `--verify`) | **B** — extend `bin/waaseyaa migrate` with `--dry-run` and `--verify`; no new command family without ADR. |
| **Q9** | Deprecation calendar for string `migrations` paths | **Hybrid (B)** — no hard removal; array form preferred from Phase 6; string path remains supported indefinitely. |

Full rationale and option tables: [`docs/specs/schema-evolution-v2.md` §15](../../docs/specs/schema-evolution-v2.md#15-ratified-resolutions-q1q9).

---

## Functional Requirements (validator aliases)

The mission's design surfaces (§A–§F) and ratified decisions (§15 Q1–Q9) are aliased
below to the FR/NFR/C scheme spec-kitty's validator recognizes. Authoring intent stays
in the existing sections; this block exists only so requirement_refs in WP frontmatter
resolve.

- FR-001 — see §A SchemaDiff data model
- FR-002 — see §B MigrationInterface v2 authoring contract
- FR-003 — see §C Composer manifest evolution
- FR-004 — see §D Migration ledger extensions
- FR-005 — see §E Execution model
- NFR-001 — see §F Test strategy
- C-001 — see §15 Ratified Resolutions Q1
- C-002 — see §15 Ratified Resolutions Q2
- C-003 — see §15 Ratified Resolutions Q3
- C-004 — see §15 Ratified Resolutions Q4
- C-005 — see §15 Ratified Resolutions Q5
- C-006 — see §15 Ratified Resolutions Q6
- C-007 — see §15 Ratified Resolutions Q7
- C-008 — see §15 Ratified Resolutions Q8
- C-009 — see §15 Ratified Resolutions Q9

---

## Purpose

Move Waaseyaa from a **migration runner** (imperative `Migration::up(SchemaBuilder)`, directory-loaded PHP files) toward a **structural evolution engine**: field-definition and storage intent produce **auditable, deterministic diffs**, with **safety gates**, **verify/replay**, and a path to **declarative Composer manifests** — without collapsing into Laravel/Symfony/Drupal “timestamp folders + hope” ergonomics.

**Architecture rule (from #529):** Waaseyaa owns diffing rules, migration generation, and safety gates; **apps** own timing and rollout of applying generated changes.

---

## Current stack (facts — coexistence, not denial)

Until replaced by phased work:

| Component | Role today |
|-----------|------------|
| `Migration` / `SchemaBuilder` / `Migrator` | Imperative migrations; batch ledger (`waaseyaa_migrations`); topological package order via `$after`. |
| `MigrationLoader` | `extra.waaseyaa.migrations` → directory; `*.php` files return `Migration` instances. |
| `SqlSchemaHandler` | `ensureTable()`, `addFieldColumns()`, bundle subtables; `deriveColumnSpec()` for field type → column (#1305). |
| `docs/specs/infrastructure.md` | Package-declared migrations convention. |

**#529 does not delete this stack in v1 of the mission.** It introduces **SchemaDiff** and companions **alongside**, then migrates authoring and execution **deliberately** (see `plan.md`).

---

## A. SchemaDiff model (design contract)

Define a **pure data** representation of structural change (no SQL strings at the core boundary). Minimum operation set — extend only with ADR when new cases appear:

| Operation | Meaning |
|-----------|---------|
| `AddColumn` | table, column name, column spec (typed; aligns with `deriveColumnSpec` / DBAL-oriented maps). |
| `DropColumn` | table, column; **destructive** — gated by policy. |
| `AlterColumn` | table, column, new spec (or typed “change class” per vendor). |
| `AddIndex` / `DropIndex` | named or anonymous indexes; composite columns. |
| `AddForeignKey` / `DropForeignKey` | referential intent; SQLite vs MySQL/Postgres feature matrix documented. |
| `RenameTable` / `RenameColumn` | explicit rename steps (not silent implicit). |
| `CompositeDiff` | ordered list of atomic ops (transaction boundary = one migration unit unless split for safety). |
| `EntityLevelDiff` | diff scoped to one `entity_type_id` base table + known subtables (`{base}__{bundle}` per bundle-scoped-storage spec). |
| `BundleLevelDiff` | subset of entity diff affecting one bundle subtable only. |

**Invariants:** Diff objects are **immutable** (readonly PHP 8.4 classes or value objects), **JSON-serializable** for fixtures/CI where practical, and **comparable** (equality / hash) for checksums.

---

## B. MigrationInterface v2 (authoring surface)

Target shape (names illustrative until ADR locks):

- **`readonly` class** (or sealed hierarchy) implementing **`MigrationInterfaceV2`** (or evolve `Migration` with versioned namespace).
- **Constructor-injected `SchemaDiff`** (or `list<SchemaDiffOp>`) — **no** body SQL.
- **No `up()` / `down()`** on the v2 interface; optional **`MigrationPlan`** separates “intent” from “apply” for dry-run.
- **Interop:** compiler produces either DBAL `Schema` mutations, `TableBuilder` steps, or a **narrow SQL DTO** generated only at the compiler seam — not hand-authored in packages.

Legacy `Migration` remains supported until deprecation window is decided (document in spec + CHANGELOG).

---

## C. Declarative `composer.json` manifest (target)

**Target** (RFC until `PackageManifest` + `MigrationLoader` accept it):

```json
"extra": {
  "waaseyaa": {
    "migrations": [
      "Waaseyaa\\Groups\\Migrations\\v1",
      "Waaseyaa\\Groups\\Migrations\\v2"
    ]
  }
}
```

Semantics to specify in design:

- **Ordered list** — explicit apply order; no reliance on filename sort alone.
- **Entries** — version namespaces (PHP 8.4 `namespace` + class discovery rules) **or** ordered path strings during transition.
- **Coexistence rule:** string value (`"migrations"`) remains valid **#1286** shape until Phase 6 removes directory scanning.

---

## D. Migration ledger extensions

Extend beyond today’s `(migration, package, batch, ran_at)`:

| Field | Purpose |
|-------|---------|
| `package` | Composer package name (unchanged). |
| `version` / `migration_id` | Stable logical id (not only display name). |
| `checksum` | Hash of canonical serialized diff or migration source (define algorithm in ADR). |
| `applied_at` | existing `ran_at` semantics. |
| `diff_hash` | Optional: hash of compiled SQL or normalized DDL for verify mode. |

**Replay:** define what “replay” means for dev (e.g. scratch DB + full migrate chain) vs prod (never silent re-apply conflicting checksum).

---

## E. Execution model

| Mode | Behavior |
|------|----------|
| **apply** | Current `Migrator::run` semantics extended to v2 plans + legacy migrations in one ordered graph (document merge). |
| **dry-run** | Emit human-readable + machine-readable diff of **would** execute SQL / DBAL ops; no ledger write. |
| **verify** | Compare live DB schema fingerprint vs expected from entity types + applied migrations; fail with diagnostic codes. |
| **replay** | Dev-only or explicit operator flag: rebuild from empty + reapply chain (document constraints). |

CLI naming: prefer extending existing `migrate` / `migrate:status` with flags (`--dry-run`, `--verify`) unless product chooses new names — avoid duplicate command surfaces without ADR.

---

## F. Test strategy

| Layer | Cases |
|-------|--------|
| **Diff engine** | Additive column, rename-like (blocked or explicit), destructive (blocked), bundle subtable, `FieldStorage::Data` vs column. |
| **Compiler** | `SchemaDiff` → SQLite SQL (first); golden-file tests for SQL text where stable. |
| **Round-trip** | Where feasible: SQL → introspected schema → diff (limited; document non-goals). |
| **Idempotency** | Applying same diff twice is no-op or explicit “already applied”. |
| **Cross-DB** | SQLite first in CI; MySQL/Postgres gates behind optional job matrix when ready. |
| **Integration** | Full kernel + sqlite file: field definition change → generated plan → migrate → verify. |

---

## Non-goals (for this epic’s first design pass)

- Replacing `ensureTable()` for base entity lifecycle in one shot.
- Admin UI for migrations.
- Automatic apply on HTTP boot.

---

## Acceptance (#529 epic)

Per GitHub: all child issues **#522, #521, #518** complete; supported schema changes produce **deterministic** migration output; **unsafe** changes blocked or surfaced with explicit operator-facing detail.

---

## Traceability

- PRs: `feat(#521): …` / `docs(#522): …` / `test(#518): …` per workflow Rule 4.
- Update `docs/specs/workflow.md` Framework milestones row for v2.0 when behavior lands.
- Keep `docs/audits/track4-sprint-sequence.md` in sync: **#529** active until epic closes; **#1310** after.
