# Research — Migration Platform v1 (Substrate in Core)

**Mission:** `migration-platform-v1-01KRCDE9` (M-002)
**Phase:** 0 — Outline & Research
**Status:** COMPLETE.
**Date:** 2026-05-13

Phase 0 establishes the decisions, alternatives, and risk envelope that the Phase 1 design (data-model, contracts, quickstart) ratifies. Every decision below is anchored to a specific spec section, FR, or ADR; every open question from spec §14 has a recommended resolution; every risk has a named mitigation.

---

## 1. Mission framing

ADR 012a (Accepted 2026-05-11) ships migration as **substrate in core**, not as a separate optional plugin. The strategic argument: WordPress is the largest single user-acquisition lever (40%+ of the web); the framework's mission promise (obsolete Drupal/Laravel/WordPress) is incoherent without an inbound bridge. The substrate must be charter §5.8 stable surface so that downstream source-reader packages (`waaseyaa-migrate-source-wordpress` and successors) can plan against a stable contract.

This mission ships the substrate only. It does NOT ship the WordPress reader — that is the next mission, in a sibling package, taking this mission's accepted-stable substrate as its foundation. Mirror-and-amend approach (consistent with M-001 entity-storage-v2 and M-006 entity-storage-translations-v1).

---

## 2. Decisions (all anchored to the ratified spec)

### D1. Plugin contracts as interfaces, with `final readonly` value objects

All three plugin contracts (`SourcePluginInterface`, `ProcessPluginInterface`, `DestinationPluginInterface`) are PHP interfaces. The data flowing through them — `SourceRecord`, `DestinationRecord`, `WriteResult`, `ProcessContext`, `SourceId`, `MigrationDefinition` — are `final readonly class` value objects with named-constructor parameters.

**Rationale:** Interfaces give us substitutability (mock in tests, replace in apps); `final readonly` value objects give us immutability and PHP 8.5 idiom alignment. No service locators, no class-string lookups (per `.claude/rules/feedback_modern_php_rules.md`).

**Alternatives considered:**
- Abstract base classes for plugins. Rejected — couples implementers to a single inheritance slot they may need for other purposes (e.g. extending a vendor's parser base).
- Mutable DTOs. Rejected — the runner passes records through process chains; mutation between processors would make order-dependence bugs invisible.

**FR anchor:** FR-001, FR-003, FR-005, FR-011.
**WP:** WP01 (interfaces + DTOs), WP02 (`MigrationDefinition`).

### D2. Plugin registration via provider capability, parallel to `HasNativeCommandsInterface`

Plugins are surfaced through `HasMigrationPluginsInterface::migrationPlugins(): array<SourcePluginInterface|ProcessPluginInterface|DestinationPluginInterface>`. Migrations are surfaced through `HasMigrationsInterface::migrations(): array<MigrationDefinition>`. Both are Composer-discovered exactly like `HasNativeCommandsInterface`.

**Rationale:** This is the established Waaseyaa idiom. Reflection-discovered surfaces are marked `@api` for the dead-code audit (`tools/phpstan/WaaseyaaEntrypointProvider.php`). One discovery pass at boot — no runtime indirection.

**Alternatives considered:**
- Container tags / service-locator pattern. Rejected per `.claude/rules/feedback_modern_php_rules.md`.
- Filesystem-only discovery. Rejected because source-reader packages (e.g. `waaseyaa-migrate-source-wordpress`) want to ship plugins inside the package boundary, not as files dropped into the consumer's repo.
- Filesystem-only fallback is still supported for one-off app migrations (FR-013 — `migration.manifest_paths`).

**FR anchor:** FR-007, FR-013.
**WP:** WP01 (`HasMigrationPluginsInterface`), WP02 (`HasMigrationsInterface` + filesystem fallback).

### D3. Reserved plugin-id namespace owned by the framework

Reserved process-plugin ids: `pass_through`, `html_sanitize`, `lookup`, `concat`, `type_coerce`, `default_value` (spec §5.4). Third-party packages MUST use a non-reserved id — recommended convention `<vendor>_<purpose>` (e.g. `wordpress_shortcode_strip`). Collisions raise `MigrationPluginCollisionException` at boot with both registering FQCNs (FR-008).

**Rationale:** Identical pattern to M-001's reserved backend-id namespace (`sql-blob`, `sql-column`, `vector`). Prevents ecosystem fragmentation on naming. The framework owns the canonical six; everything else is namespaced.

**Alternatives considered:** Free-for-all id namespace (rejected — fragmentation); single global registry of all ids ever (rejected — operational burden).

**FR anchor:** FR-008, spec §5.4.
**WP:** WP01.

### D4. `migration_id_map` schema fixed at v1; reverse index added

Schema per spec §8.1: `(migration_id, source_id_hash, destination_entity_type, destination_uuid, last_imported_at, last_run_id, source_record_hash)` with `PRIMARY KEY (migration_id, source_id_hash)` and a secondary index `migration_id_map__entity` on `(destination_entity_type, destination_uuid)`.

**Rationale:** The reverse index is needed for `import:rollback` and for cross-migration ID resolution. Both indexes are stable surface (FR-025); future changes follow charter §5.4 (schema evolution rules).

**Alternatives considered:**
- Single id-map for all migrations (rejected — collisions; per-migration table forks were briefly considered and rejected for tooling burden).
- UUID-only key (rejected — `source_id_hash` is the canonical key because not all sources have UUIDs).

**FR anchor:** FR-025, FR-028, FR-031.
**WP:** WP04.

### D5. `SourceId::hash()` = sha256 of canonical form

Canonical form: `sourceType` concatenated with the JSON-encoded sorted-key associative array of "key fields" declared by `sourceIdFor()`. Encoding flags: `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`. Key values coerced to `string` before hashing.

**Rationale:** Deterministic across PHP minors, locales, and machines (FR-027). sha256 is a stable choice — 64 hex chars fit comfortably in a `TEXT` column on SQLite/Postgres. Explicit encoding flags prevent locale-driven drift (silent re-import of every record on upgrade was the leading risk).

**Alternatives considered:**
- xxhash / blake3 — faster but not bundled with PHP stdlib; sha256 is universally available.
- Hashing the raw record (no canonical form) — rejected because PHP associative-array order is insertion-order-dependent.

**FR anchor:** FR-027.
**WP:** WP04.

### D6. `source_record_hash` separate from `source_id_hash` (Q2 resolution)

`source_id_hash` is identity — derived from key fields only, declared by `sourceIdFor()`. `source_record_hash` is change-detection — sha256 of the full record's canonical form. Re-runs compare `source_record_hash`: unchanged → skip; changed → update; absent → create.

**Rationale:** Splitting identity from content lets the framework do correct idempotency without forcing source plugins to declare every field as "key." Spec §14 Q2 recommended this; we adopt it.

**Alternatives considered:**
- Single combined hash (rejected — every field becomes part of identity, defeats change detection).
- Per-field hashing with row-level join (rejected — overengineered; v0.x defers).

**FR anchor:** FR-027, FR-031, spec §8.3.
**WP:** WP04.

### D7. `EntityDestination` integrates via storage coordinator, never directly

`EntityDestination::write()` does NOT hold a backend handle. It calls `EntityRepository::save()` (or the equivalent storage coordinator entry point shipped in M-001 WP05). The coordinator handles backend fan-out, lifecycle event dispatch, and revision creation.

**Rationale:** ADR 012a explicitly chose this layering. Reimplementing fan-out in `EntityDestination` would diverge from the rest of the entity-save path — same bugs in two places. Per `.claude/rules/entity-storage-invariant.md`: "All entity persistence MUST flow through `EntityRepository` / `EntityStorageCoordinator`."

**Alternatives considered:**
- Direct backend writes from `EntityDestination` (rejected — bypasses lifecycle, breaks revisions, violates storage invariant).
- A separate "import mode" coordinator (rejected — duplication; the existing coordinator becomes `isImport`-aware via the `SaveContext` flag instead).

**FR anchor:** FR-019, FR-021.
**WP:** WP05.

### D8. Bundle resolution at write time, not at registration

`EntityDestination`'s constructor takes `bundle: 'foo'`. The bundle resolves when `write()` is called against a `DestinationRecord` — not when the `MigrationDefinition` is registered. This means bundle-config changes do not require re-registering migrations.

**Rationale:** Bundles are entity-type configuration shipped by apps; migrations are framework-level. Decoupling these timings means a bundle rename does not force a migration re-deploy.

**Alternatives considered:** Eager resolution at registration (rejected — registration order would need to follow entity-type registration, which it currently doesn't).

**FR anchor:** FR-024.
**WP:** WP05.

### D9. Resume granularity: per-record commit by default, ≤100-record batches as an opt-in

Per-record commit is the default — every record append commits a row to `migration_run_state`. Operators can opt in to ≤100-record batches via a `--batch-size=N` flag (`N` ≤ 100) for throughput-sensitive runs. Resume reads the highest committed `position` per `(migration_id, run_id)` and re-iterates from there.

**Rationale:** Per-record commits give the strongest correctness guarantee at the cost of write amplification. Batching is opt-in for operators who measure and choose differently. Spec FR-038 allows both modes explicitly.

**Alternatives considered:** Snapshot-checkpoint (rejected — overengineered for v0.x; the entity-storage transactional model is sufficient).

**FR anchor:** FR-037, FR-038.
**WP:** WP07.

### D10. Rollback is best-effort, logged, with reverse-creation walk

`import:rollback` walks the id-map in reverse-creation order (LIFO over `last_imported_at`). Per-record errors during rollback are logged on the `entity.lifecycle` channel but do NOT halt the walk (FR-044). After completion, `import:status` reflects per-record rollback success/failure.

**Rationale:** A halt-on-first-error rollback leaves the system in a more-confused state than a complete best-effort rollback. Operators get a structured log they can inspect; the id-map reflects which records remained.

**Alternatives considered:** Transactional rollback (rejected — destination entities span multiple backends; a single transaction is not possible).

**FR anchor:** FR-043, FR-044.
**WP:** WP08.

### D11. Concurrency via filesystem lock; no auto stale-lock recovery

Lock file: `storage/migration-locks/<migration-id>.lock`, containing the PID. `import:run` acquires the lock before iterating; releases on normal exit and on `SIGTERM`/`SIGINT` via a `pcntl_signal` handler (where available — Windows degrades to normal-exit only). Stale locks (PID not running) are NOT auto-cleared. Operators delete the lock file manually after verifying the PID is dead. The `MigrationConcurrencyException` surface carries the lock-file path and the holding PID so operators can act.

**Rationale:** Auto-clear creates a worse failure mode — PID reuse can silently allow two concurrent runs against the same migration. Manual recovery is documented in spec §9.3 and FR-062. Operator-friendly errors solve the UX gap.

**Alternatives considered:** Database lock row (rejected — extra dependency on transactional behavior across SQLite/Postgres); Redis-based lock (rejected — adds a hard dependency on Redis for a use-case that's filesystem-local).

**FR anchor:** FR-061, FR-062.
**WP:** WP09.

### D12. CLI verb namespace is `import:*`, not `migrate:*`

Six commands: `import:run`, `import:run-all`, `import:status`, `import:rollback`, `import:reset`, `import:resume`. Charter §11 Q11 names a future-ADR question on whether all top-level verbs should consolidate; this mission does NOT block on that consolidation.

**Rationale:** ADR 012a's primary reason to choose `import:*` is to avoid namespace collision with the existing schema-migration `migrate:*` verbs (`migrate:make`, `migrate:run`, `migrate:rollback`, `migrate:status`). Both verb spaces would otherwise have a `:rollback` and `:status` that mean entirely different things to the same operator.

**Alternatives considered:** `data:import:*` (rejected — too verbose); `cms:import:*` (rejected — Waaseyaa is a framework, not a CMS by branding).

**FR anchor:** spec §9, FR-032..FR-040.
**WP:** WP06.

---

## 3. Open-question resolutions (mirror spec §14)

### Q1. Plugin-id namespace policy → reserved + non-reserved app prefix

**Resolution:** Same policy as backend ids (D3). Framework reserves the six process-plugin ids. App-defined plugins use a non-reserved id; recommended convention `<vendor>_<purpose>`. Collision check at boot via `MigrationPluginCollisionException` (FR-008).
**Owning WP:** WP01.

### Q2. Idempotency hash strategy → key-field hash + separate record hash

**Resolution:** `sourceIdFor()` returns a `SourceId` carrying the sourceType + sorted associative-array of key fields. `SourceId::hash()` hashes only those. A separate `source_record_hash` is computed by the runner from the full record's canonical form and stored alongside the id-map row for change detection. (D5, D6.)
**Owning WP:** WP04.

### Q3. Process plugin chain ordering → array-order only in v0.x

**Resolution:** Array-order chain only. No cross-package `Pipeline::after()/before()` mechanism in v0.x. Revisit if community process plugins require cross-package ordering.
**Owning WP:** WP01 / WP02 (chain semantics defined in `MigrationDefinition`).

### Q4. Memory budget → configurable per migration, default 256MB, warn at 120%

**Resolution:** `MigrationDefinition::$memoryBudgetBytes` (default `256 * 1024 * 1024`). Runner samples `memory_get_peak_usage(true)` at each record; emits a structured warning on the `migration.deprecation` channel when peak exceeds budget by 20%. The conformance suite (FR-051) tests streaming-source memory bounds against a 50MB ceiling regardless of definition.
**Owning WP:** WP02 (definition shape), WP06 (runner sampling).

### Q5. Error budget → default `error_rate_warn 0.01 / error_rate_halt 0.10`; `--halt-on-error` overrides to halt-on-1

**Resolution:** `MigrationDefinition::$errorRateWarn` (default 0.01) and `$errorRateHalt` (default 0.10), both as floats in [0, 1]. Runner samples error rate after each record; emits warning on the `migration.deprecation` channel when warn threshold crossed; raises `MigrationAbortedException` when halt threshold crossed. `--halt-on-error` flag overrides both to 0 (halt on first error) per FR-047.
**Owning WP:** WP06.

### Q6. WP05 external dependency timing → N/A; prereq MET

**Resolution:** Not applicable. M-001 (`entity-storage-v2-01KRCDDC`) shipped its WP04 (lifecycle events) and WP08 (revisionable storage API) on 2026-05-11 (squash `509e31fb7`). M-006 (`entity-storage-translations-v1-01KRF0FQ`) shipped 2026-05-13 (squash `0f7e1809a`). The `BeforeSaveEvent`, `AfterSaveEvent`, `BeforeDeleteEvent`, `AfterDeleteEvent`, and `RevisionableEntityStorageInterface` symbols are present on `main`. WP05 can start at any time after WP01+WP04 ship internally.
**Owning WP:** WP05 (consumes the prereq).

### Q7. `import:*` CLI namespace → keep; do NOT block on consolidation ADR

**Resolution:** Stay on `import:*`. The future-ADR question on top-level verb consolidation (charter §11 Q11) is independent of this mission. (D12.)
**Owning WP:** WP06.

### Q8. Migration as a config entity? → NO for v0.x

**Resolution:** No. Migrations remain PHP objects in code. Admin-editable migrations are a future ADR, separate from CMI (ADR 018). Out of scope for v0.x and v1.x of this substrate.
**Owning WP:** N/A — explicit non-goal.

---

## 4. Sequencing summary

Restate spec §11.1 with the entity-storage-v2 external prereq status updated.

```
                          ┌──────────────────────────────────────────┐
                          │ entity-storage-v2 WP04+WP08              │
                          │ STATUS: MET (M-001 squashed 509e31fb7,    │
                          │ M-006 squashed 0f7e1809a)                │
                          └──────────────┬───────────────────────────┘
                                         ▼ (no external block)
WP01 (contracts) ──┬──► WP02 (definition + DAG) ─┐
                   ├──► WP03 (process plugins)   │
                   ├──► WP04 (id-map + SourceId) ┼──► WP05 (EntityDestination)
                   │                              │         ▼
                   ├──► WP10 (conformance) ───────┘    WP06 (import:run + run-all + status + dry-run)
                   │                                          ▼
                   └─────────────────► WP08 (rollback) ──┐  ┌──► WP07 (resume)
                                                          │  │
                                                          │  └──► WP09 (concurrency)
                                                          ▼
                                          WP06+07+08+10 ──► WP11 (e2e validation)
                                                                 ▼
                                                              WP12 (docs + charter §5.8)
```

### Parallelizable WPs after WP01

WP02, WP03, WP04, WP10 are mutually independent and can run in parallel.

After WP04 + WP05: WP06 → (WP07, WP08, WP09 parallel).

WP11 closes validation; WP12 closes the mission.

### Cross-mission coordination — NONE OUTSTANDING

The only hard external prerequisite (entity-storage-v2 WP04 + WP08) is **MET**. This mission can run end-to-end against `main` without any further coordination with sibling missions.

---

## 5. Downstream consumers

- **`waaseyaa-migrate-source-wordpress`** (next mission, separate package) — first first-party source-reader; implements `WordPressPostSource`, `WordPressUserSource`, etc. against this substrate. Spec §13 sketches scope.
- **`wp_users_to_accounts`, `wp_posts_to_teachings`** etc. — concrete `MigrationDefinition` instances shipped alongside the WordPress reader. They consume this mission's `LookupProcessor` for cross-migration ID resolution.
- **Drupal 7 reader** — second-priority source-reader package; later sibling mission.
- **Drupal 10+ reader** — third-priority source-reader package; later sibling mission.
- **`migration_test_widget`** entity type (test fixture only) — exercises the contract during WP11 validation; lives in `packages/migration/tests/Fixtures/`; not autoloaded in production.

---

## 6. Out-of-scope, restated (mirrors spec §1.2)

This mission does NOT ship:

- The WordPress source reader (separate package, separate mission).
- The Drupal 7 source reader (separate package, separate mission).
- The Drupal 10+ source reader (separate package, separate mission).
- An admin UI for migration management. CLI-only in v0.x. Q8 resolution.
- Incremental / continuous sync. Source readers running as ongoing watchers are out of scope for v0.x.
- Real-time conflict resolution. Concurrent source mutation during a migration is operator concern, not framework concern.
- A Drupal-style admin Migrate Tools UI.
- Content promotion between Waaseyaa environments. That is a fixture/seed concern, not a migration concern.

---

## 7. Acceptance restatement

Mirror spec §12 criteria 1–8 in this mission's voice:

1. **All 12 WPs merged.** Tracked in mission `status.json`; merge-time enforced by `bin/check-mission-status` (existing repo policy).
2. **Every FR-001..FR-062 covered by tests.** PHPUnit suite tagged per-WP; a per-FR coverage matrix lives in `tasks/wp-XX-*.md` once tasks are materialized.
3. **Conformance suite green for `CsvSource` and `EntityDestination`.** WP10 + WP11 deliverables.
4. **WP11 e2e validation: 1000-record CSV → entity with resume + rollback.** Throughput ≥1000 records/min; resume proof (FR-054) interrupts at record 500, completes the remaining 500, final state: 1000 entities created, 0 duplicates; full rollback removes all (FR-053, FR-054, FR-055).
5. **Charter §5.8 added.** WP12; mirrored on `public-surface-map.md` / `public-surface-map.php` with tier `stable` and status `present`.
6. **First upgrade-guide entry shipped** at `docs/upgrades/waaseyaa-alpha-<X>-to-<Y>.md` per FR-059.
7. **Author guides shipped** at `docs/extension-authoring/migration-source-readers.md` and `migration-process-plugins.md`.
8. **Substrate ready for the WordPress reader mission.** Definition: a hypothetical `waaseyaa-migrate-source-wordpress` package can `composer require` Waaseyaa, declare `MigrationDefinition`s via `HasMigrationsInterface`, and run `import:run-all` without framework changes.

---

## 8. Risk register

Seven risks; each has a mitigation owner and a tripwire.

### R1. ID-map corruption on interrupted runs

**Risk:** A crash mid-`EntityDestination::write()` leaves an entity in storage but no id-map row → re-run creates a duplicate.
**Mitigation:** Write the id-map row inside the same DBAL transaction as the entity save (FR-029). The id-map UPSERT happens before `EntityRepository::save()` returns; rollback on save failure rolls back the id-map row too.
**Tripwire:** WP05 contract test asserts that simulated save failure leaves zero id-map rows.
**Owner:** WP05.

### R2. Source plugin memory growth on lazy-iteration violation

**Risk:** A source plugin author writes `records()` as a generator but accidentally builds an eager array somewhere along the chain — peak memory spikes during large imports.
**Mitigation:** `SourceConformanceTestCase` runs a fixture larger than 50MB through the source and asserts peak memory stays under 50MB (FR-051).
**Tripwire:** Conformance test failure is a hard gate on shipping any new source-reader package.
**Owner:** WP10.

### R3. Rollback fails mid-walk

**Risk:** During `import:rollback`, one record's destination delete fails (access policy, external dependency, race). The walk must NOT halt; status must reflect partial rollback.
**Mitigation:** Best-effort rollback (D10, FR-044). Per-record errors logged on `entity.lifecycle` channel; walk continues; final `import:status` shows residual id-map rows.
**Tripwire:** WP08 integration test simulates a delete failure on the 500th of 1000 records and asserts the remaining 500 still get walked.
**Owner:** WP08.

### R4. Clock-based collisions on `last_imported_at`

**Risk:** Two records imported in the same millisecond on a fast SSD have identical `last_imported_at` — reverse-creation order is ambiguous.
**Mitigation:** `last_run_id` is a UUIDv7 (timestamp-ordered) populated per *record*, not per *run*. Reverse-creation walk orders by `(last_imported_at DESC, last_run_id DESC)` — tiebreaker preserves arrival order.
**Tripwire:** WP04 test inserts two id-map rows with identical timestamps and asserts deterministic walk order.
**Owner:** WP04.

### R5. `EntityDestination` access-policy denial mid-run

**Risk:** A record's destination entity-type policy returns `Forbidden` on `create` mid-run — typed exception must propagate cleanly; `--halt-on-error` must respect it.
**Mitigation:** `EntityDestination::write()` checks `create` via `Gate::denies()` before save (FR-020). Denial raises `DestinationWriteException`. Runner increments error counter; `--halt-on-error` halts immediately; default mode logs and continues.
**Tripwire:** WP05 contract test runs a denying policy and asserts the typed exception.
**Owner:** WP05.

### R6. Backend fan-out partial-failure during import

**Risk:** A multi-backend entity type (e.g. `dictionary_entry` with `sql-column` body + `vector` embedding) hits a `PartialSaveException` from the coordinator — half-written.
**Mitigation:** The coordinator's `PartialSaveException` handling (shipped in M-001 WP05 / extended in M-006) is reused unchanged. `EntityDestination` does not re-implement fan-out. If the coordinator raises, `EntityDestination` raises `DestinationWriteException` wrapping the coordinator's exception; the id-map row is NOT written (R1's transactional guarantee).
**Tripwire:** WP05 integration test simulates a vector-backend write failure on a multi-backend entity type and asserts no id-map row + no orphan sql-column row.
**Owner:** WP05.

### R7. `SaveContext::isImport()` flag piping bug

**Risk:** The flag is set on `SaveContext` by `EntityDestination` but not propagated into lifecycle event payloads → subscribers can't detect imports → cache-warm-on-save fires for every imported record → cold cache thrashes during bulk import.
**Mitigation:** `EntityStorageCoordinator` threads the `SaveContext` reference into `BeforeSaveEvent` and `AfterSaveEvent` constructors. WP05 integration test attaches a recording subscriber and asserts `$event->context->isImport() === true` for every event during a migration run.
**Tripwire:** Test fails if the flag is false in either event.
**Owner:** WP05.

---

## 9. Scope fence (non-negotiable)

The following are explicit non-goals for this mission:

- **No WordPress reader.** Sibling mission.
- **No Drupal readers.** Sibling missions.
- **No admin UI.** Out of scope until v1.x admin-editable-migrations ADR (if ever).
- **No incremental sync.** Out of scope.
- **No real-time conflict resolution.** Out of scope.
- **No content promotion between Waaseyaa environments.** That is a fixture/seed concern.
- **No `migrate:*` namespace overlap.** Stays on `import:*` (D12).
- **No service-locator-based plugin discovery.** Provider capabilities only (D2).
- **No mutable record DTOs.** All value objects are `final readonly` (D1).

---

## 10. Evidence trail

Spec mirror: `kitty-specs/migration-platform-v1-01KRCDE9/spec.md` — 625 lines, 62 FRs.
Canonical doctrine: `docs/specs/migration-platform-v1.md` (mirrored).
Governing ADR: `docs/adr/012a-migration-substrate-in-core.md` (Accepted 2026-05-11).
Related ADRs: `010-multi-backend-field-storage.md`, `011-entity-lifecycle-events.md`, `016-revisions-first-class.md`.
Charter: `docs/specs/stability-charter.md` — §10 lists ADR 012a in governing ADRs; this mission proposes §5.8.
Sibling missions: `entity-storage-v2-01KRCDDC` (MET); `entity-storage-translations-v1-01KRF0FQ` (MET).
Project rules: `.claude/rules/feedback_modern_php_rules.md`, `.claude/rules/entity-storage-invariant.md`, `.claude/rules/data-freshness.md`, `.claude/rules/shell-compatibility.md`.
Handoff lesson: `spec-kitty-next-claude-handoff-after-m006.md` (justifies implementer override).
