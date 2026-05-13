# Implementation Plan: Migration Platform v1 — Substrate in Core

**Branch:** `main` (mission landing; planning done in place on `main`; no worktree at plan stage)
**Date:** 2026-05-13
**Mission:** `migration-platform-v1-01KRCDE9` (M-002)
**Spec:** `kitty-specs/migration-platform-v1-01KRCDE9/spec.md`
**Governing ADR:** [ADR 012a](../../docs/adr/012a-migration-substrate-in-core.md) — substrate in core; source readers as packages; WordPress reader first-party priority.
**Related ADRs:** [010 multi-backend field storage](../../docs/adr/010-multi-backend-field-storage.md); [011 entity lifecycle events](../../docs/adr/011-entity-lifecycle-events.md); [016 revisions first-class](../../docs/adr/016-revisions-first-class.md).
**Charter governance:** [`docs/specs/stability-charter.md`](../../docs/specs/stability-charter.md). This mission **proposes a new §5.8 "Migration platform"** (additive amendment; WP12). All shipped symbols are additive — no pre-existing API breaks.

---

## Summary

Waaseyaa today has no path *into* itself from existing CMS installs. ADR 012a (Accepted 2026-05-11) reversed the prior "migration out of scope" position because the framework's mission promise (obsolete Drupal/Laravel/WordPress) is incoherent without an inbound bridge. This mission ships the **substrate** — plugin contracts, manifest format, ID-map primitives, default `EntityDestination`, CLI runner, conformance suite — that every future source-reader package (WordPress first, then Drupal 7/10+) will sit on. It does **not** ship the WordPress reader itself; that is the next mission.

Approach:

1. Establish three plugin interfaces (`SourcePluginInterface`, `ProcessPluginInterface`, `DestinationPluginInterface`) as stable surface via charter §5.8 amendment; register via two provider capabilities (`HasMigrationPluginsInterface`, `HasMigrationsInterface`) parallel to existing `HasNativeCommandsInterface`.
2. Ship a `MigrationDefinition` `final readonly` value object with dependency-graph + cycle detection at registration time.
3. Ship `EntityDestination` riding the entity-storage coordinator (ADR 010), dispatching lifecycle events (ADR 011), creating revisions where applicable (ADR 016). Re-runs are idempotent via `SourceId` hashing + `migration_id_map`.
4. Ship six essential process plugins (`PassThroughProcessor`, `HtmlSanitizeProcessor`, `LookupProcessor`, `ConcatProcessor`, `TypeCoerceProcessor`, `DefaultValueProcessor`) with a reserved plugin-id namespace.
5. Ship the `import:*` CLI verb namespace (six commands) with filesystem-lock concurrency guard, per-record progress persistence (`migration_run_state`), resume, and rollback.
6. Ship a reusable conformance suite (`SourceConformanceTestCase`, `DestinationConformanceTestCase`) plus a framework-internal `CsvSource` fixture used both by the suite and by WP11 end-to-end validation.
7. Close with charter §5.8 amendment, source-reader-author guide, process-plugin-author guide, upgrade guide, and cookbook (WP12).

External prerequisite status: **MET.** M-001 (`entity-storage-v2-01KRCDDC`, squash `509e31fb7`, 2026-05-11) and M-006 (`entity-storage-translations-v1-01KRF0FQ`, squash `0f7e1809a`, 2026-05-13) have landed. `BeforeSaveEvent`, `AfterSaveEvent`, `BeforeDeleteEvent`, `AfterDeleteEvent`, and `RevisionableEntityStorageInterface` are present on `main`. WP05 has no external block remaining.

---

## Technical Context

**Language/Version:** PHP 8.5+ (project-mandated minimum; see `composer.json`). Strict types in every file. PHP 8.5 idioms — `final readonly class`, named-args, asymmetric visibility, `#[\NoDiscard]` where applicable.

**Primary Dependencies:**
- Doctrine DBAL (canonical SQL abstraction — `Waaseyaa\Foundation\Database\DBALDatabase`). The id-map and run-state tables are created via Waaseyaa migrations and queried via the DBAL query builder, not raw PDO (per `.claude/rules/entity-storage-invariant.md`).
- Symfony Console (the six `import:*` commands).
- Symfony EventDispatcher (consumed via the storage coordinator; the migration runner does not dispatch its own framework-level events in v1).
- Symfony Uid (run-id generation — UUIDv7).
- Composer-based provider discovery (`HasMigrationPluginsInterface`, `HasMigrationsInterface` follow the existing `HasNativeCommandsInterface` pattern; reflection-discovered surfaces marked `@api` per `.claude/rules/feedback_modern_php_rules.md`).

**Storage:** SQLite for dev/CI; Postgres and SQLite in production. The id-map schema (spec §8.1) and the proposed `migration_run_state` schema (FR-038) target both backends. All entity persistence goes through `EntityRepository` / `SqlStorageDriver` / `EntityStorageCoordinator` — never raw PDO.

**Testing:**
- PHPUnit 10.5 (project-mandated; **no `-v` flag** — PHPUnit 10.5 rejects it).
- The conformance suite is two abstract `TestCase` base classes (`SourceConformanceTestCase`, `DestinationConformanceTestCase`) usable in both first-party tests and third-party package tests. Pattern mirrors M-001's backend-contract tests.
- `CsvSource` lives under `packages/migration/tests/Fixtures/CsvSource.php` and is `autoload-dev`-scoped (consumers installing with `--no-dev` never see it — see CLAUDE.md gotcha "Never put classes that extend dev-only deps under autoload").
- Integration tests live at `tests/Integration/PhaseN/` per project convention; WP11 e2e validation goes under `tests/Integration/Migration/`.
- Pest is NOT used.

**Target Platform:** PHP-FPM behind Caddy (web); `bin/waaseyaa` CLI (operator). The CLI is a `cli-server` / `cli` SAPI — the six `import:*` commands run under `cli`.

**Project Type:** Single project (PHP monorepo). New top-level package `packages/migration/` lives at **Layer 3 (Services)** in the CLAUDE.md layer table — it imports from Layer 0 (foundation, queue, validation) and Layer 1 (entity, entity-storage, access). The `import:*` CLI commands extend `packages/cli/` at Layer 6. No upward edges; layer compliance is enforced by `bin/check-package-layers`.

**Performance Goals:**
- WP11 validation throughput: **≥1000 records/min** for the 1000-record CSV → entity end-to-end import (spec §12, FR-053).
- Coordinator overhead per write: **<5ms p95** (write path is dominated by entity-storage; the migration runner adds id-map lookup + run-state row only).
- Lock acquisition: **<50ms p95** (filesystem lock at `storage/migration-locks/<migration-id>.lock`; FR-061).
- Memory: streaming sources operate within `MigrationDefinition::$memoryBudgetBytes` (default 256MB; warn at 120%; resolved Q4 in research §2).

**Constraints:**
- Charter §5.8 (new) stable surface — every public symbol named in spec §4 is a contract; future breaking changes require charter amendment.
- Layer rule: `packages/migration/` is **Layer 3 (Services)** — it imports from Layer 0/1 and is consumed by Layer 6 CLI. No upward imports introduced.
- No `psr/log` — use `Waaseyaa\Foundation\Log\LoggerInterface`. Reserve `error_log()` only for last-resort fallbacks inside logging infrastructure.
- No service locators or class-string registries — registration through provider capabilities only (per `.claude/rules/feedback_modern_php_rules.md`).
- No Illuminate / Laravel facades.
- All entity persistence MUST flow through `EntityRepository` / `EntityStorageCoordinator` — `EntityDestination::write()` does NOT touch a raw `Connection` (per `.claude/rules/entity-storage-invariant.md`).

**Scale/Scope:**
- 12 work packages (spec §11).
- 62 functional requirements (FR-001..FR-062; spec §3).
- Public-surface deliverables (spec §4): 3 plugin interfaces, 2 provider capabilities, 1 manifest value object (`MigrationDefinition`), 6 process-plugin concrete classes, 1 destination concrete (`EntityDestination`), 4 DTO value objects (`SourceId`, `SourceRecord`, `DestinationRecord`, `WriteResult`, `ProcessContext`), 2 table schemas (`migration_id_map`, `migration_run_state`), 8 exception classes, 6 CLI commands, 2 conformance test bases, 1 log channel constant, 1 `SaveContext::isImport()` extension.
- One downstream mission unblocked (`waaseyaa-migrate-source-wordpress`).

**Validation entity:** the WP11 e2e test uses a **test-fixture entity type** `migration_test_widget` registered under `packages/migration/tests/Fixtures/`. It is NOT a real Minoo entity; it is not autoloaded in production. This keeps the mission framework-internal and avoids coupling to any application-layer schema.

**Agent assignment override:** Spec §16 names `implementer: sonnet / reviewer: opus`. **This plan overrides implementer to `opus` (`subagent_type: claude`)** for every WP; reviewer stays `opus`. Rationale: the M-006 post-merge handoff (`spec-kitty-next-claude-handoff-after-m006.md`) documented sonnet's repeated "hallucinated completion" failure mode on big WPs — sonnet returned `--result success` without actually landing the code. The lesson applies here because every WP in this mission touches stable surface that cannot be silently incomplete. Recorded in Charter Check below as PASS-with-note.

---

## Charter Check

Mission proposes new charter §5.8 "Migration platform" (additive amendment; landed in WP12). All shipped symbols are additive; zero pre-existing surface breaks. Gates evaluated against the charter as it stands today plus the proposed amendment:

| Gate | Status | Evidence |
|---|---|---|
| Stable surface enumerated | PASS | Spec §4 lists every symbol with its charter §5.x anchor. Data-model §1 expands each with file path + WP. |
| Governing ADR accepted | PASS | ADR 012a Accepted 2026-05-11. ADRs 010/011/016 (related) all accepted. |
| Charter §5.8 amendment drafted | PASS (deferred to WP12) | Spec §4 footer: "Charter amendment required: new §5.8 'Migration platform'… Drafted as part of WP12." |
| External dependencies satisfiable | PASS | **M-001 (entity-storage-v2) shipped 2026-05-11 (`509e31fb7`); M-006 (translations) shipped 2026-05-13 (`0f7e1809a`).** Lifecycle events + revisionable storage API present on `main`. WP05 unblocked. |
| Validation consumer is framework-internal | PASS | Reference `CsvSource` fixture lives in `packages/migration/tests/Fixtures/` under `autoload-dev`. NOT a first-party composer package. Validation entity is the `migration_test_widget` test fixture — no Minoo / app coupling. |
| Agent assignments resolved | PASS-with-note | **Implementer overridden to `opus`** per M-006 handoff lesson. Reviewer stays `opus`. Recorded in mission `meta.json` at task-generation time. |
| Additive-only stable-surface deltas | PASS | All FRs ship new symbols. The single touch to pre-existing surface is `SaveContext::isImport(): bool` — an additive method on a class shipped in M-001. No deletions, no signature changes. |
| Layer compliance | PASS | `packages/migration/` is Layer 3 (imports Layer 0/1). `packages/cli/` extension is Layer 6 (imports Layer 3 for commands; already does). `bin/check-package-layers` will validate at WP01 merge. |
| No `psr/log`, no service locators, no class-string registries | PASS | Logging via `Waaseyaa\Foundation\Log\LoggerInterface`. Registration via provider capabilities. Reflection-discovered surfaces marked `@api`. |
| Conformance suite covers FR-049..FR-052 | PASS (lands WP10) | Two abstract `TestCase` base classes; reusable in third-party packages. |

**Unresolved clarifications:** none. All eight §14 open questions have explicit resolutions, locked in by research §2 and consumed by named WPs. See `research.md` §2.

---

## Project Structure

### Documentation (this feature)

```
kitty-specs/migration-platform-v1-01KRCDE9/
├── spec.md                            # Canonical mission spec (625 lines)
├── plan.md                            # THIS FILE
├── research.md                        # Phase 0 — decisions D1–D12, open-question resolutions Q1–Q8, risks, sequencing, scope fence
├── data-model.md                      # Phase 1 — stable-surface symbols, plugin registration, manifest shape, storage shape, lifecycle/resume/rollback semantics, layering, charter anchors
├── quickstart.md                      # Phase 1 — three reader views: migration author, source-reader package author, operator
├── contracts/                         # Phase 1 — normative interface specs
│   ├── source-plugin.md
│   ├── process-plugin.md
│   ├── destination-plugin.md
│   ├── migration-definition.md
│   └── cli-runner.md
├── checklists/
│   └── requirements.md                # Created during /specify (present)
├── research/                          # Phase 0 evidence (created during /specify)
├── tasks/                             # Phase 2 — DO NOT pre-populate; created by /spec-kitty.tasks
├── meta.json
├── mission-events.jsonl
└── status.events.jsonl
```

### Source code (repository)

New package + extensions to existing packages:

```
packages/migration/                                                    # NEW — Layer 3 (Services)
├── composer.json                                                       # waaseyaa/migration
├── src/
│   ├── Plugin/
│   │   ├── SourcePluginInterface.php                                  # FR-001..FR-002 (WP01)
│   │   ├── ProcessPluginInterface.php                                 # FR-003..FR-004 (WP01)
│   │   ├── DestinationPluginInterface.php                             # FR-005..FR-006 (WP01)
│   │   └── ReservedPluginIds.php                                      # spec §5.4 (WP01)
│   ├── Capability/
│   │   ├── HasMigrationPluginsInterface.php                           # FR-007 (WP01)
│   │   └── HasMigrationsInterface.php                                 # FR-013 (WP02)
│   ├── Definition/
│   │   ├── MigrationDefinition.php                                    # FR-011..FR-012 (WP02)
│   │   └── MigrationRegistry.php                                      # FR-013..FR-017 (WP02)
│   ├── Graph/
│   │   ├── DependencyGraph.php                                        # FR-014..FR-015 (WP02)
│   │   └── CycleDetector.php                                          # FR-015 (WP02)
│   ├── Process/
│   │   ├── PassThroughProcessor.php                                   # spec §5.4 (WP03)
│   │   ├── HtmlSanitizeProcessor.php                                  # spec §5.4 (WP03)
│   │   ├── LookupProcessor.php                                        # spec §5.4 (WP03)
│   │   ├── ConcatProcessor.php                                        # spec §5.4 (WP03)
│   │   ├── TypeCoerceProcessor.php                                    # spec §5.4 (WP03)
│   │   └── DefaultValueProcessor.php                                  # spec §5.4 (WP03)
│   ├── IdMap/
│   │   ├── SourceId.php                                               # FR-026..FR-027 (WP04)
│   │   ├── MigrationIdMap.php                                         # FR-025, FR-028..FR-031 (WP04)
│   │   └── Schema/MigrationIdMapMigration.php                         # FR-025 (WP04)
│   ├── Destination/
│   │   └── EntityDestination.php                                      # FR-018..FR-024 (WP05)
│   ├── Runner/
│   │   ├── MigrationRunner.php                                        # FR-032..FR-040 (WP06)
│   │   ├── RunState.php                                               # FR-038 (WP07)
│   │   ├── RunStateStore.php                                          # FR-038 (WP07)
│   │   └── Schema/MigrationRunStateMigration.php                      # FR-038 (WP07)
│   ├── Rollback/
│   │   └── RollbackWalker.php                                         # FR-041..FR-044 (WP08)
│   ├── Concurrency/
│   │   ├── FilesystemLock.php                                         # FR-061..FR-062 (WP09)
│   │   └── MigrationConcurrencyException.php                          # FR-061 (WP09)
│   ├── Exception/                                                      # FR-045 (WP01–WP09 as each ships)
│   │   ├── MigrationCycleException.php
│   │   ├── MigrationPluginCollisionException.php
│   │   ├── MigrationDependencyMissingException.php
│   │   ├── SourceReadException.php
│   │   ├── ProcessException.php
│   │   ├── DestinationWriteException.php
│   │   └── MigrationAbortedException.php
│   ├── DTO/
│   │   ├── SourceRecord.php                                           # FR-002 (WP01)
│   │   ├── DestinationRecord.php                                      # FR-006 (WP01)
│   │   ├── WriteResult.php                                            # FR-006 (WP01)
│   │   └── ProcessContext.php                                         # FR-004 (WP01)
│   ├── Log/Channels.php                                                # `migration.deprecation` constant (WP01)
│   └── ServiceProvider.php                                             # registers capabilities + commands
├── testing/                                                            # autoload-dev only
│   ├── SourceConformanceTestCase.php                                  # FR-049, FR-051 (WP10)
│   └── DestinationConformanceTestCase.php                             # FR-050..FR-051 (WP10)
└── tests/
    ├── Unit/...                                                        # per-class unit tests
    ├── Fixtures/
    │   ├── CsvSource.php                                              # FR-052 (WP10)
    │   └── MigrationTestWidget.php                                    # validation entity fixture (WP11)
    └── Integration/
        └── EndToEndCsvImportTest.php                                  # FR-053..FR-055 (WP11)

packages/entity-storage/src/SaveContext.php                            # MODIFY — add isImport(): bool (FR-022, WP05)

packages/cli/src/Command/
├── ImportRunCommand.php                                                # FR-032 (WP06)
├── ImportRunAllCommand.php                                             # FR-033 (WP06)
├── ImportStatusCommand.php                                             # FR-034 (WP06)
├── ImportRollbackCommand.php                                           # FR-035 (WP08)
├── ImportResetCommand.php                                              # FR-036 (WP08)
└── ImportResumeCommand.php                                             # FR-037 (WP07)

docs/specs/migration-platform.md                                        # NEW — canonical post-mission spec (FR-056, WP12)
docs/extension-authoring/migration-source-readers.md                    # NEW (FR-057, WP12)
docs/extension-authoring/migration-process-plugins.md                   # NEW (FR-058, WP12)
docs/upgrades/waaseyaa-alpha-<X>-to-<Y>.md                              # NEW entry (FR-059, WP12)
docs/cookbook/writing-a-custom-migration.md                             # NEW (FR-060, WP12)
docs/specs/stability-charter.md                                         # MODIFY — add §5.8 (WP12)
public-surface-map.md / public-surface-map.php                          # MODIFY — list every §5.8 symbol with tier:stable, status:present (WP12)
```

---

## Phase 0 — Outline & Research

**Status:** COMPLETE.

Artifacts:
- `kitty-specs/migration-platform-v1-01KRCDE9/research.md` — 12 decisions (D1–D12) anchored to spec §1–§16, 8 open-question resolutions (Q1–Q8) per spec §14, 7-risk register, sequencing summary with external prereq marked MET, downstream consumers, scope fence, acceptance restatement.
- `kitty-specs/migration-platform-v1-01KRCDE9/research/` — source register + evidence log carried forward from `/specify`.

**Unresolved clarifications:** none. All eight §14 open questions have explicit recommended resolutions in research §2 — each is consumed by a named WP. Specifically: Q1 reserved namespace owned by WP01; Q2 `sourceIdFor()` key fields + `source_record_hash` split owned by WP04; Q3 array-order chain only owned by WP01/WP02; Q4 `MigrationDefinition::$memoryBudgetBytes` default 256MB warn at 120% owned by WP02/WP06; Q5 error-rate thresholds `warn 0.01 / halt 0.10` configurable + `--halt-on-error` overrides to halt-on-1 owned by WP06; Q6 N/A (external prereq MET); Q7 stay on `import:*` owned by WP06; Q8 no admin UI in v0.x — out of scope.

---

## Phase 1 — Design & Contracts

**Status:** COMPLETE (this command).

### 1.1 Data model

See `data-model.md`. Covers: stable-surface symbols with FR + WP traces; plugin registration model; `MigrationDefinition` shape; storage shape (`migration_id_map` + proposed `migration_run_state`); EntityDestination write/re-run/rollback paths; streaming-source semantics; resume semantics; rollback semantics; concurrency lock; error model; layering check; charter anchors.

### 1.2 Contracts to generate

The five contract documents under `contracts/` are the normative interface specs:

| Contract file | Owning WP(s) | Content |
|---|---|---|
| `contracts/source-plugin.md` | WP01, WP10 | `SourcePluginInterface` signature + `SourceRecord` + `SourceId` + streaming requirement + `sourceIdFor()` determinism + conformance hooks. |
| `contracts/process-plugin.md` | WP01, WP03 | `ProcessPluginInterface` signature + `ProcessContext` + chain semantics + `lookup` callable + reserved-id namespace. |
| `contracts/destination-plugin.md` | WP01, WP05, WP08 | `DestinationPluginInterface` signature + `DestinationRecord` + `WriteResult` + write/rollback/lookup semantics + access-check invariant + `SaveContext::isImport()` wiring. |
| `contracts/migration-definition.md` | WP02 | `MigrationDefinition` final readonly value object + process-map shape + `HasMigrationsInterface` capability + manifest-path discovery + cycle detection + dependency-missing error. |
| `contracts/cli-runner.md` | WP06, WP07, WP08, WP09 | Six CLI commands with full flag matrices, exit codes, output formats, concurrency lock contract, resume protocol, dry-run + limit + halt-on-error semantics. |

These are generated alongside this plan.

### 1.3 Quickstart

See `quickstart.md`. Three reader views: (A) migration author declaring a CSV → User migration; (B) source-reader package author shipping a hypothetical XML reader; (C) operator running `import:run-all` → `import:status` → `import:resume` → `import:rollback`.

---

## Post-Phase-1 Charter Re-check

| Gate | Status | Evidence |
|---|---|---|
| Stable surface enumerated | PASS | Data-model §1 expands spec §4 with file paths + WP traces. |
| Governing ADR accepted | PASS | Unchanged from above. |
| Charter §5.8 amendment drafted | PASS (deferred to WP12) | Unchanged. |
| External dependencies satisfiable | PASS | Unchanged — M-001 + M-006 merged. |
| Agent override resolved | PASS-with-note | Implementer = opus; reviewer = opus. |
| Additive-only deltas | PASS | Only existing-surface touch is `SaveContext::isImport()` — additive method. |
| Layer compliance | PASS | Data-model §11 traces every import edge; all flow downward. |
| FR coverage in Phase 1 docs | PASS | Each FR cited at least twice across plan + research + data-model + contracts + quickstart. |
| No new clarifications surfaced | PASS | Phase 1 design did not surface new unknowns. |

**No gaps surfaced.** Phase 1 design did not invent new surface beyond spec §4. The only new mechanical element is the proposed `migration_run_state` table schema (a v0.x design choice consuming FR-038 — research §1 D11; data-model §4.2); the schema is contained inside `packages/migration/` and is mission-internal infrastructure, not user-facing stable surface, so it does not require charter §5.8 listing.

---

## Phase 2 — Task generation

**NOT EXECUTED BY THIS COMMAND.** Run `/spec-kitty.tasks` to materialize the 12 work packages from spec §11 into `kitty-specs/migration-platform-v1-01KRCDE9/tasks/`. The mission tasks pass should pick up: the 12-WP decomposition from spec §11; the dependency graph from §11.1; the parallelization hints from §11.2; the implementer-override (opus) recorded in this plan; and the §14 open-question resolutions from research.md.

---

## Complexity Tracking

Six areas of elevated complexity. Each is in a single WP's scope and is testable in isolation; none cross WP boundaries unsupervised.

| # | Area | Why it's hard | Mitigation | WP |
|---|---|---|---|---|
| 1 | Reflective fan-out in `EntityDestination::write()` across heterogeneous backends | A `dictionary_entry` with a `sql-column` body + a `vector` embedding must write through the storage coordinator once and have the coordinator dispatch per backend. Errors in one backend must not leave another half-written. | Rely on `EntityStorageCoordinator` (M-001 WP05 / M-006) which already handles backend fan-out + `PartialSaveException`. `EntityDestination` calls `EntityRepository::save()` and lets the coordinator do the work. Don't reimplement fan-out. (FR-019) | WP05 |
| 2 | `SourceId::hash()` stability across PHP versions, locales, JSON encoding flags | sha256(canonical_form) must be byte-identical across Linux/macOS, PHP 8.5 + future minors, all locales, and across machines on the same migration. Locale-sensitive sorting or `JSON_UNESCAPED_*` flag drift would silently re-import every record on upgrade. | Canonical form: sort keys with `ksort($keys, SORT_STRING)`; encode with explicit `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`; convert all key values to `string` before hashing. Cover with a "hash-stability vector" test in conformance suite. (FR-027) | WP04 |
| 3 | Resume correctness when interrupted mid-batch | FR-038 allows per-record OR ≤100-record batched commits. Resume must compute the last *committed* position, not the last *attempted* position. Off-by-one on batch boundary re-imports up to 99 records (idempotent but observable in `import:status`). | `RunState` rows update only after commit; the runner re-reads the highest committed `position` on resume. Per-record mode (default) has no batch edge case. Batched mode wraps a Doctrine DBAL `Connection::transactional()` block. (FR-038) | WP07 |
| 4 | Rollback ordering must be reverse-creation | If migration A's destination depends on migration B's entities (via `LookupProcessor`), rolling back B before A while still inside one `import:rollback` invocation could orphan A's references. Per-migration rollback walks a single id-map; cross-migration rollback ordering is operator concern. | Document that `import:rollback` operates on a single migration (FR-035, FR-043). Cross-migration rollback ordering is the operator's responsibility — reverse-dependency order, mirroring `import:run-all`'s dependency-graph order. Capture in cookbook (WP12, FR-060). | WP08 |
| 5 | Stale-PID lock detection vs accidental concurrent runs | Operators want the framework to auto-clear stale locks. Auto-clear could silently allow two simultaneous runs if a parent process forked and the lock PID is reused. | Do NOT auto-clear. `MigrationConcurrencyException` exposes the lock-file path + PID. The operator manually deletes the lock file after verifying the PID is dead. Document the recovery in FR-062 and quickstart §C. | WP09 |
| 6 | `SaveContext::isImport()` flag piping through `EntityStorageCoordinator` | The flag must be set on the `SaveContext` that the coordinator constructs *and* propagated to every lifecycle event payload so subscribers see the flag. Non-aware subscribers ignore it; aware subscribers branch (e.g. skip cache warming). A bug here is silent — the flag stays false, subscribers do extra work, no test catches it. | `EntityDestination::write()` passes an explicit `SaveContext` with `isImport: true` (FR-022). Coordinator threads it through to event constructors. Add a coordinator test asserting the flag round-trips end-to-end into both `BeforeSaveEvent` and `AfterSaveEvent`. | WP05 |

---

## Stop point

This plan ends after Phase 1. Phase 2 (work package materialization) is deferred to `/spec-kitty.tasks` per the prompt's mandatory stop point.
