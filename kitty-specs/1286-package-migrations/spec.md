# Mission spec: #1286 — Package-level migrations (design-first)

**GitHub:** [waaseyaa/framework#1286](https://github.com/waaseyaa/framework/issues/1286)  
**Milestone:** Track 4 — Schema evolution  
**Predecessor:** [#1305](https://github.com/waaseyaa/framework/issues/1305) closed — explicit `deriveColumnSpec()` contract documented in `docs/specs/field/column-derivation.md`.

---

## 1. Purpose

Deliver a **package-level migration convention** that:

- Uses **`extra.waaseyaa.migrations`** as the **declared** integration surface (Composer manifest policy already treats `extra.waaseyaa` as authoritative for discovery).
- Runs cleanly through **`Migrator` + `waaseyaa migrate:up`** on fresh installs.
- **Stops** the anti-pattern of **every-boot** `SqlSchemaHandler::addFieldColumns()` in providers (OIDC is the concrete victim; see issue body and #1285).
- Positions Waaseyaa for **Track 4 / v2.0 schema evolution** without copying 2010-era “scan a folder, hope filenames sort” as the *final* form of truth.

---

## 2. Current engine (facts — do not pretend this away)

Today the framework already has:

| Piece | Role |
|--------|------|
| `PackageManifestCompiler` | Collects `extra.waaseyaa.migrations` per **Composer package name** → manifest `migrations` map. |
| `MigrationLoader` | For each `package => path`, resolves a **directory**, **`glob('*.php')`**, **`sort($files)`**, `require $file`, expects instance of `Waaseyaa\Foundation\Migration\Migration`. |
| `Migration` (abstract) | `up(SchemaBuilder $schema): void`, optional `down()`, **`$after`** list for topological ordering. |
| `Migrator` + `MigrationRepository` | Batches, **`waaseyaa_migrations`** ledger (`migration`, `package`, `batch`, `ran_at`). |

So **#1286 is not greenfield**: the first shippable increment **extends and documents** this pipeline, then **evolves** it deliberately toward the north star below.

---

## 3. North-star architecture (2026-correct — target state)

These properties are **design goals** for Waaseyaa to **advance** rather than re-skin Laravel/Drupal/Symfony/WordPress patterns:

1. **Declarative ordering** — Order of application must not depend on opaque filesystem sort alone. Composer (or a generated lock manifest) should declare **intent** (ordered migration identities or version lanes).
2. **Composable units** — Prefer **named, versioned migration identities** over “everything in this directory”.
3. **Deterministic + auditable** — Ledger already exists; extend toward **checksum / content hash** optional column or sidecar when we tighten supply-chain story (non-blocking for M1286 acceptance).
4. **Structural intent over raw SQL** — Prefer **`SchemaBuilder` / schema objects** (already the `Migration::up` contract) and, when **#529** lands, **`SchemaDiff`-style** deltas aligned with entity/field definitions — not ad-hoc string DDL as the default author experience.
5. **PHP 8.4-native** — `readonly` where appropriate, **typed properties**, **enums** for version lanes when we introduce them; avoid “stringly typed class names in JSON” **without** also having static analysis or codegen hooks.
6. **Static analyzability** — Any manifest indirection (PHP files vs class list) should remain **PHStan-visible** at the package boundary; avoid runtime-only magic beyond the current `require` boundary.

**Explicit non-goals (from #1286 + product reality):**

- Do **not** replace kernel **`ensureTable()`** for base entity tables.
- No **migration admin UI**.
- Do **not** block M1286 on full **SchemaDiff** — that is **#529 / v2.0** territory; this mission **interfaces** to it.

---

## 4. Gap between north star and today

| Today | Risk | Direction |
|--------|------|-----------|
| Directory glob + filename sort | Hidden ordering, “timestamp prefix” culture | **Phase A:** document **required naming + `$after`**; **Phase B:** optional explicit manifest list in `composer.json` once `PackageManifest` + `MigrationLoader` support it. |
| `require` returns `Migration` | Works, weak static story | Keep for Phase A; consider **class-name list** or **single registrar PHP** returning ordered array in Phase B. |
| No checksum in ledger | Replay/verify limited | Add to backlog / #529 companion, not #1286 acceptance. |

---

## 5. Composer contract (evolution path)

**Today (supported):** per package, a **string** path relative to package root (or absolute in monorepo dev), e.g. `"migrations"` — see `packages/queue`, `notification`, `scheduler`, `ai-observability`.

**Proposed extension (design only until implemented):** allow an **ordered JSON array** of either:

- Relative directory paths (backward compatible), and/or
- Future: **FQCN** or **`Waaseyaa\…\Migration\…` version namespaces** once loader understands them.

**Until `MigrationLoader` is extended**, any “array of namespaces” example in ADR/spec must be labeled **RFC** — implementation PR must update `PackageManifest` typing + compiler merge rules + loader in one coherent change.

---

## 6. Execution model (CLI)

Reuse existing commands (`migrate:up`, rollback, status) wired through `ConsoleKernel`’s `\Closure` provider — **no duplicate `waaseyaa:migrate` namespace** unless product decides to alias for UX.

Add **design requirement** for follow-on WPs:

- **`--dry-run`** — emit planned operations without committing (may be stubbed with “not supported on SQLite path” if technically blocked — document truthfully).
- **`--verify`** — compare expected schema fingerprint vs DB (ties to #529; can be no-op stub with message until diff engine exists).

---

## 7. Acceptance mapping (GitHub #1286)

| Criterion | Mission interpretation |
|-----------|---------------------------|
| ≥1 framework package ships a migration via `extra.waaseyaa.migrations` | Prefer **OIDC** if ready, else **document queue as reference** + add second exemplar if issue demands “framework” specifically. |
| `migrate:up` clean on fresh install | Integration test or CI job path **must** be specified in `plan.md`. |
| Spec documents convention | **`docs/specs/infrastructure.md`** subsection or new **`docs/specs/package-migrations.md`** + cross-link from entity-system / workflow if needed. |
| Remove OIDC `addFieldColumns` from `boot()` | **Separate WP or PR** after migration lands (issue already says follow-up). |

---

## 8. Test strategy

- **Unit:** `MigrationLoader` with manifest fixture containing a temp package path (existing test style in `MigrationLoaderTest`).
- **Integration:** boot minimal kernel / sqlite `:memory:`, run migrator, assert table/column from package migration.
- **Regression:** OIDC (or chosen package) no longer relies on boot-time schema mutation once follow-up merges.

---

## 9. Traceability

- PR titles: `feat(#1286): …` per workflow Rule 4.
- Update **`docs/audits/track4-sprint-sequence.md`** active anchor from #1305 → **#1286** when implementation starts.

---

## 10. Relation to #529 and #1310

- **#529** — Schema diffing v2 / migration generation: **consumes** a stable package-migration story; do not collapse into #1286.
- **#1310** — Deploy noise: **after** baseline schema/migration story is clear.
