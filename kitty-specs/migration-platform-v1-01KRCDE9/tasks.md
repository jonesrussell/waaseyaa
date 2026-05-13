# Work Packages: migration-platform-v1-01KRCDE9 (M-002)

**Mission ID:** `01KRCDE9ZXK2JEFPT6THSBVKNY`
**Mission slug:** `migration-platform-v1-01KRCDE9`
**Mission number:** TBD (assigned by `spec-kitty merge`)
**Friendly name:** Migration Platform v1 — Substrate in Core
**Status:** Work packages materialized — ready for finalize-tasks.
**Spec:** [`spec.md`](./spec.md) (625 lines, 62 FRs in §3, 12-WP decomposition in §11)
**Plan:** [`plan.md`](./plan.md)
**Research:** [`research.md`](./research.md) (12 decisions, 8 open-question resolutions, 7 risks)
**Data model:** [`data-model.md`](./data-model.md)
**Contracts:** [`contracts/`](./contracts/) — five normative interface specs
**Governing ADR:** [012a](../../docs/adr/012a-migration-substrate-in-core.md) — substrate in core; source readers as packages.
**Charter governance:** [`docs/specs/stability-charter.md`](../../docs/specs/stability-charter.md). This mission **proposes a new §5.8 "Migration platform"** (additive amendment delivered by WP12). All shipped symbols are additive — no pre-existing API breaks.

## Branch contract

- **planning_base_branch:** `main`
- **merge_target_branch:** `main`
- **Planning work:** done in place on `main`; no worktree at plan stage.
- **Execution work:** per-lane worktrees created by `spec-kitty agent action implement <WPxx>` at execution time.

## Agent assignments

- **implementer:** `opus` (`subagent_type: claude`) — **overrides spec.md §16** (`sonnet`) per the M-006 handoff lesson (`spec-kitty-next-claude-handoff-after-m006.md`): sonnet's hallucinated-completion failure mode on big WPs is unacceptable for the stable-surface scope of this mission.
- **reviewer:** `opus`
- **arbiter:** N/A — opus is implementer and reviewer; rejection cycles escalate to user-as-arbiter after N=2.

---

## Overview

This mission delivers the Migration Platform v1 — a Layer 3 framework substrate that lets Waaseyaa accept inbound content from foreign sources (WordPress, Drupal, CSV, etc.) without coupling the framework to any specific source format. The mission ships **62 functional requirements** (FR-001..FR-062, spec §3) split across **12 work packages** with **~67 subtasks** (T001..T067).

The 12 WPs decompose along the platform's natural seams:

- **WP01–WP04** establish the substrate: plugin contracts, manifest format, process plugins, id-map.
- **WP05** ships the default `EntityDestination` integrating with M-001's storage coordinator.
- **WP06–WP09** ship the runner and CLI: run, status, resume, rollback, reset, lock.
- **WP10** ships the conformance test suite that third-party plugins must pass.
- **WP11** validates end-to-end with a 1000-record CSV → entity round-trip including resume + rollback.
- **WP12** closes the mission with documentation and the charter §5.8 amendment.

After WP01 lands, **WP02, WP03, WP04, WP10 can run in parallel**. After WP04+WP05, **WP06 unblocks WP07, WP08, WP09 in parallel**. WP11 closes the validation gate; WP12 closes the mission.

**MVP cut (smallest publishable substrate):** WP01 + WP02 + WP04 + WP10. After those four merge, the framework has plugin contracts, manifest format, id-map, and conformance bases. A third party could ship a `SourcePluginInterface` implementation and prove it passes conformance — though without `EntityDestination` (WP05) and the CLI (WP06), it's framework-internal use only. Not the recommended ship cut; documented to clarify the dependency floor.

**External prerequisite:** M-001 (entity-storage-v2, squash-merged at `0f7e1809a`) shipped lifecycle events (WP04) and the revisionable storage API (WP08). **MET.** No further cross-mission coordination needed. See WP05's Dependencies section for the explicit citation.

---

## Subtask index

| ID    | Description                                                                       | WP   | Parallel | FRs                                                |
|-------|-----------------------------------------------------------------------------------|------|----------|----------------------------------------------------|
| T001  | Scaffold `packages/migration/` composer.json + ServiceProvider + workspace wiring | WP01 |          | FR-007                                              | [D] |
| T002  | `SourcePluginInterface` + `SourceRecord` DTO + `SourceId` stub                    | WP01 | [P]      | FR-001, FR-002                                      | [D] |
| T003  | `ProcessPluginInterface` + `ProcessContext` DTO                                   | WP01 | [P]      | FR-003, FR-004, FR-010                              | [D] |
| T004  | `DestinationPluginInterface` + `DestinationRecord` + `WriteResult` DTOs           | WP01 | [P]      | FR-005, FR-006                                      | [D] |
| T005  | `HasMigrationPluginsInterface` + `PluginRegistry` + reserved ids + log channel    | WP01 |          | FR-007, FR-008, FR-009                              | [D] |
| T006  | `MigrationPluginCollisionException`                                               | WP01 | [P]      | FR-008, FR-045                                      | [D] |
| T007  | Unit tests for plugin contracts + registry                                        | WP01 |          | FR-001..FR-010                                      | [D] |
| T008  | `MigrationDefinition` value object                                                | WP02 |          | FR-011, FR-012, FR-016                              | [D] |
| T009  | `HasMigrationsInterface` provider capability                                      | WP02 | [P]      | FR-013                                              | [D] |
| T010  | `FilesystemManifestLoader`                                                        | WP02 | [P]      | FR-013                                              | [D] |
| T011  | `MigrationRegistry` + `DependencyGraph` + `CycleDetector`                         | WP02 |          | FR-013, FR-014, FR-015, FR-017                      | [D] |
| T012  | `MigrationCycleException` + `MigrationDependencyMissingException`                 | WP02 | [P]      | FR-014, FR-015, FR-045                              | [D] |
| T013  | Integration test: discovery + ServiceProvider wiring                              | WP02 |          | FR-011..FR-017                                      | [D] |
| T014  | `PassThroughProcessor`                                                            | WP03 | [P]      | FR-010                                              | [D] |
| T015  | `HtmlSanitizeProcessor`                                                           | WP03 | [P]      | FR-010                                              | [D] |
| T016  | `LookupProcessor`                                                                 | WP03 | [P]      | FR-010                                              | [D] |
| T017  | `ConcatProcessor`                                                                 | WP03 | [P]      | FR-010                                              | [D] |
| T018  | `TypeCoerceProcessor`                                                             | WP03 | [P]      | FR-010                                              | [D] |
| T019  | `DefaultValueProcessor`                                                           | WP03 | [P]      | FR-010                                              | [D] |
| T020  | `ProcessException` + reserved-id parity test                                      | WP03 |          | FR-010, FR-045                                      | [D] |
| T021  | Real `SourceId` (replaces WP01 stub) + `CanonicalForm`                            | WP04 |          | FR-026, FR-027                                      | [D] |
| T022  | `migration_id_map` schema + migration file                                        | WP04 | [P]      | FR-025                                              | [D] |
| T023  | `MigrationIdMap` repository                                                       | WP04 |          | FR-028, FR-029, FR-030, FR-031                      | [D] |
| T024  | `SourceReadException`                                                             | WP04 | [P]      | FR-045                                              | [D] |
| T025  | Unit tests                                                                        | WP04 |          | FR-025..FR-031                                      | [D] |
| T026  | Integration test: schema portability + transactional safety                       | WP04 |          | FR-029                                              | [D] |
| T027  | Test-fixture entity type `migration_test_widget` (+ revisionable variant)         | WP05 |          | FR-018..FR-024 substrate                            | [D] |
| T028  | Add `SaveContext::isImport()` (cross-cutting, M-001-owned file)                   | WP05 |          | FR-022                                              | [D] |
| T029  | `EntityDestination` class + factory                                               | WP05 |          | FR-018, FR-019, FR-020, FR-021, FR-023, FR-024      | [D] |
| T030  | `DestinationWriteException`                                                       | WP05 | [P]      | FR-045                                              | [D] |
| T031  | Integration test: non-revisionable round-trip                                     | WP05 |          | FR-018..FR-022, FR-029..FR-031                      | [D] |
| T032  | Integration test: revisionable variant                                            | WP05 |          | FR-023                                              | [D] |
| T033  | `RunOptions` + `RunReport` value objects                                          | WP06 | [P]      | FR-039, FR-040, FR-047                              | [D] |
| T034  | `ProcessChainExecutor`                                                            | WP06 | [P]      | FR-010                                              | [D] |
| T035  | `MigrationRunner` + `MigrationAbortedException`                                   | WP06 |          | FR-032, FR-039, FR-040, FR-046, FR-047, FR-048      | [D] |
| T036  | `ImportRunCommand`                                                                | WP06 |          | FR-032, FR-039, FR-040, FR-047                      | [D] |
| T037  | `ImportRunAllCommand`                                                             | WP06 | [P]      | FR-033                                              | [D] |
| T038  | `ImportStatusCommand`                                                             | WP06 | [P]      | FR-034                                              | [D] |
| T039  | `migration_run_state` schema + migration file                                     | WP07 |          | FR-038                                              | [D] |
| T040  | `MigrationRunState` repository                                                    | WP07 |          | FR-037, FR-038                                      | [D] |
| T041  | Extend `MigrationRunner` to write progress + `runResume()`                        | WP07 |          | FR-037, FR-038, FR-046                              | [D] |
| T042  | `ImportResumeCommand`                                                             | WP07 |          | FR-037                                              | [D] |
| T043  | Integration test: full resume flow                                                | WP07 |          | FR-037, FR-038                                      | [D] |
| T044  | `RollbackReport` value object                                                     | WP08 | [P]      | FR-044                                              | [D] |
| T045  | `RollbackWalker`                                                                  | WP08 |          | FR-041, FR-043, FR-044                              | [D] |
| T046  | `ImportRollbackCommand`                                                           | WP08 |          | FR-035, FR-043, FR-044                              | [D] |
| T047  | `ImportResetCommand`                                                              | WP08 | [P]      | FR-036                                              | [D] |
| T048  | Integration test: full rollback flow                                              | WP08 |          | FR-035, FR-036, FR-041..FR-044                      | [D] |
| T049  | `MigrationLock` class                                                             | WP09 |          | FR-061, FR-062                                      | [D] |
| T050  | `MigrationConcurrencyException`                                                   | WP09 | [P]      | FR-061, FR-062, FR-045                              | [D] |
| T051  | Wire lock into CLI commands (WP06+WP07+WP08)                                      | WP09 |          | FR-061                                              | [D] |
| T052  | Integration test: concurrent acquisition + signal handling                        | WP09 |          | FR-061, FR-062                                      | [D] |
| T053  | `CsvSource` reference fixture                                                     | WP10 |          | FR-052                                              | [D] |
| T054  | `SourceConformanceTestCase` abstract base                                         | WP10 | [P]      | FR-049, FR-051                                      | [D] |
| T055  | `DestinationConformanceTestCase` abstract base                                    | WP10 | [P]      | FR-050, FR-051                                      | [D] |
| T056  | `ReferenceSourceConformanceTest`                                                  | WP10 |          | FR-049, FR-051, FR-052                              | [D] |
| T057  | `ReferenceDestinationConformanceTest`                                             | WP10 |          | FR-050, FR-051                                      | [D] |
| T058  | `users-1000.csv` fixture                                                          | WP11 | [P]      | FR-053 substrate                                    | [D] |
| T059  | `UsersCsvToWidgetsMigration` definition fixture                                   | WP11 | [P]      | FR-053 substrate                                    | [D] |
| T060  | Integration test: full E2E (import + resume + rollback + idempotency)             | WP11 |          | FR-053, FR-054, FR-055                              | [D] |
| T061  | Operator-path E2E via CommandTester                                               | WP11 |          | FR-053, FR-054, FR-055                              | [D] |
| T062  | `docs/specs/migration-platform.md` canonical spec                                 | WP12 | [P]      | FR-056                                              |
| T063  | `docs/extension-authoring/migration-source-readers.md`                            | WP12 | [P]      | FR-057                                              |
| T064  | `docs/extension-authoring/migration-process-plugins.md`                           | WP12 | [P]      | FR-058                                              |
| T065  | `docs/cookbook/migration-first-cut.md`                                            | WP12 | [P]      | FR-060                                              |
| T066  | `docs/upgrades/waaseyaa-alpha-X-to-Y.md`                                          | WP12 | [P]      | FR-059                                              |
| T067  | Charter §5.8 + CLAUDE.md + public-surface-map                                     | WP12 |          | charter §5.8                                        |

**FR coverage:** 62 unique FRs (FR-001..FR-062 — the spec has gaps at FR-049 through FR-052 being conformance, FR-053..FR-055 validation, FR-056..FR-060 documentation, FR-061..FR-062 concurrency, then nothing beyond FR-062). Each FR appears in exactly one WP's `requirement_refs`.

**Subtask total:** T001..T067 (67 subtasks). `[P]` marks subtasks safe to do in parallel within a single WP's lane.

---

## Work packages

### WP01 — Plugin contracts + provider capability + registration

**Goal:** Stand up the `packages/migration/` package at Layer 3 and ship the three plugin interfaces, four DTO value objects, provider-capability discovery, and the collision exception. This WP unblocks the entire mission.

**Priority:** p1

**Success criteria:** All 10 FRs (FR-001..FR-010) covered; new public symbols all carry `@api`; `bin/check-package-layers` green; full PHPUnit suite green; `composer dump-autoload --optimize` clean.

**Subtasks**
- [x] T001 Scaffold composer.json + ServiceProvider + workspace wiring (WP01)
- [x] T002 SourcePluginInterface + SourceRecord + SourceId stub (WP01)
- [x] T003 ProcessPluginInterface + ProcessContext (WP01)
- [x] T004 DestinationPluginInterface + DestinationRecord + WriteResult (WP01)
- [x] T005 HasMigrationPluginsInterface + PluginRegistry + reserved ids (WP01)
- [x] T006 MigrationPluginCollisionException (WP01)
- [x] T007 Unit tests for contracts + registry (WP01)

**Implementation sketch:** Pattern after `packages/seo/composer.json` for the manifest. Establish the `Waaseyaa\Migration\Testing\` autoload-dev namespace now so WP10 can populate `testing/` later. The `SourceId` stub in T002 is a stop-gap; WP04 replaces it with the real implementation.

**Parallel opportunities:** T002, T003, T004, T006 are independent and safe to parallelize within the lane.

**Dependencies:** None (mission entry point).

**Owned files:** see `tasks/WP01-plugin-contracts.md` frontmatter (~17 entries including root `composer.json` modification).

**Risks:** `SourceId` stub leak (R1 — mitigated by WP04's first subtask); reserved-id list drift between WP01's constants and WP03's plugins (R4 — mitigated by WP03's parity test).

**Estimated prompt size:** ~330 lines.

**Prompt file:** [`tasks/WP01-plugin-contracts.md`](./tasks/WP01-plugin-contracts.md)

---

### WP02 — MigrationDefinition + discovery + dependency graph

**Goal:** Ship the manifest format and the boot-time registry that discovers migrations from provider capabilities and filesystem paths, builds the DAG, and detects cycles.

**Priority:** p1

**Success criteria:** All 7 FRs (FR-011..FR-017) covered; cycle-detection algorithm produces useful error paths; topological order is deterministic across runs.

**Subtasks**
- [x] T008 MigrationDefinition value object (WP02)
- [x] T009 HasMigrationsInterface provider capability (WP02)
- [x] T010 FilesystemManifestLoader (WP02)
- [x] T011 MigrationRegistry + DependencyGraph + CycleDetector (WP02)
- [x] T012 MigrationCycleException + MigrationDependencyMissingException (WP02)
- [x] T013 Integration test: discovery + ServiceProvider wiring (WP02)

**Implementation sketch:** `MigrationDefinition` is `final readonly class`. Cycle detection is classical DFS with three-color marking — Kahn's algorithm does not produce cycle paths. The `ServiceProvider` from WP01 grows a second registry binding.

**Parallel opportunities:** T009, T010, T012 are independent within the lane.

**Dependencies:** WP01.

**Owned files:** see `tasks/WP02-migration-definition.md` frontmatter (~12 entries).

**Risks:** Cycle-detector false positives on diamond patterns (R1 — mitigated by a dedicated test); filesystem-discovery silent empty paths (R2 — mitigated by info-level logging).

**Estimated prompt size:** ~320 lines.

**Prompt file:** [`tasks/WP02-migration-definition.md`](./tasks/WP02-migration-definition.md)

---

### WP03 — Essential process plugins

**Goal:** Ship the six framework-reserved process plugins (PassThrough, HtmlSanitize, Lookup, Concat, TypeCoerce, DefaultValue) so non-trivial migrations are expressible.

**Priority:** p1

**Success criteria:** Each plugin's `id()` equals its `ReservedPluginIds` constant; HtmlSanitize handles XSS attempts; chain composition is delivered by WP06 — this WP ships only the units.

**Subtasks**
- [x] T014 PassThroughProcessor (WP03)
- [x] T015 HtmlSanitizeProcessor (WP03)
- [x] T016 LookupProcessor (WP03)
- [x] T017 ConcatProcessor (WP03)
- [x] T018 TypeCoerceProcessor (WP03)
- [x] T019 DefaultValueProcessor (WP03)
- [x] T020 ProcessException + reserved-id parity test (WP03)

**Implementation sketch:** Each plugin is `final readonly class`. HtmlSanitize prefers `ezyang/htmlpurifier` (if in vendor); falls back to a DOMDocument allowlist. The parity test in T020 asserts `ReservedPluginIds::ALL` matches the shipped set exactly.

**Parallel opportunities:** All six plugin subtasks (T014–T019) are independent within the lane.

**Dependencies:** WP01.

**Owned files:** see `tasks/WP03-process-plugins.md` frontmatter (8 entries — 6 plugins + ProcessException + parity test).

**Risks:** HtmlSanitize falls behind real WordPress HTML (R1 — documented gap, addressed by sibling mission); TypeCoerce ambiguity on numeric strings (R2 — documented as expected).

**Estimated prompt size:** ~340 lines.

**Prompt file:** [`tasks/WP03-process-plugins.md`](./tasks/WP03-process-plugins.md)

---

### WP04 — ID-mapping + SourceId + idempotency primitives

**Goal:** Replace WP01's `SourceId` stub with the real implementation. Ship the `migration_id_map` schema, the repository, `CanonicalForm`, and `SourceReadException`.

**Priority:** p1

**Success criteria:** Hash determinism proven; `walkReverseCreation()` orders with `last_run_id` as the tied-timestamp tiebreaker; `transactional()` atomicity verified.

**Subtasks**
- [x] T021 Real SourceId (replaces stub) + CanonicalForm (WP04)
- [x] T022 migration_id_map schema + migration file (WP04)
- [x] T023 MigrationIdMap repository (WP04)
- [x] T024 SourceReadException (WP04)
- [x] T025 Unit tests (WP04)
- [x] T026 Integration test: schema portability + transactional safety (WP04)

**Implementation sketch:** `SourceId::hash()` returns sha256 of `CanonicalForm::encode([sourceType, keys])`. The schema migration uses M-001's `MigrationInterface`. `MigrationIdMap` uses `DatabaseInterface` directly (not raw PDO) — per `.claude/rules/entity-storage-invariant.md`, id-map is a join/supporting table, not an entity.

**Parallel opportunities:** T022 and T024 are independent.

**Dependencies:** WP01.

**Owned files:** see `tasks/WP04-id-mapping.md` frontmatter (10 entries). **File-ownership split with WP07:** WP04 owns `MigrationIdMapSchema.php`; WP07 owns `MigrationRunStateSchema.php` — explicit per-file ownership.

**Risks:** Hash drift between v1 and any future v2 canonical form (R1 — locked in by `CanonicalForm` tests); clock collisions on `last_imported_at` (R2 — mitigated by `last_run_id` UUIDv7 tiebreaker); cross-DB upsert syntax (R3 — SQLite primary target).

**Estimated prompt size:** ~340 lines.

**Prompt file:** [`tasks/WP04-id-mapping.md`](./tasks/WP04-id-mapping.md)

---

### WP05 — EntityDestination + storage coordinator integration

**Goal:** The mission's highest-risk WP. Ship `EntityDestination` that writes through M-001's `EntityRepository` + `EntityStorageCoordinator`, fires lifecycle events, creates initial revisions on revisionable types, and atomically updates the id-map.

**Priority:** p1

**Success criteria:** All 7 FRs (FR-018..FR-024) covered; `BeforeSaveEvent` + `AfterSaveEvent` carry `SaveContext::isImport === true` during imports; revisionable variant creates a new revision on changed re-run only.

**Subtasks**
- [x] T027 Test-fixture entity type migration_test_widget (+ revisionable variant) (WP05)
- [x] T028 Add SaveContext::isImport() — cross-cutting, M-001-owned file (WP05)
- [x] T029 EntityDestination class + factory (WP05)
- [x] T030 DestinationWriteException (WP05)
- [x] T031 Integration test: non-revisionable round-trip (WP05)
- [x] T032 Integration test: revisionable variant (WP05)

**Implementation sketch:** `EntityDestination::write()` flows through `EntityRepository::save()` exclusively (no raw PDO per `.claude/rules/entity-storage-invariant.md`). Write + id-map upsert wrapped in `DBALDatabase::transactional()`. The `SaveContext::isImport()` extension is additive (default `false`); preserves existing call sites.

**Parallel opportunities:** T030 is independent.

**Dependencies:** WP01, WP04.

**External:** MET — M-001 (`entity-storage-v2-01KRCDDC`, squash-merge `0f7e1809a`) shipped the lifecycle events and revisionable storage API in its work-package programme. No further cross-mission blocker.

**Owned files:** see `tasks/WP05-entity-destination.md` frontmatter (7 entries). **Cross-cutting modification:** T028 also adds `isImport(): bool` to `packages/entity-storage/src/SaveContext.php` (M-001-owned file); the file is NOT in `owned_files` to avoid ownership conflicts — documented in the cross-cutting modifications section below.

**Risks:** `SaveContext` shape divergence from data-model.md §1.9 (R1 — mitigated by halt-and-escalate if shape differs); backend fan-out partial failure (R2 — `PartialSaveException` propagates); `isImport` flag piping bug (R3 — asserted by T031 Test A); `MigrationIdMap::deleteByHash()` API expansion into WP04-owned class (R4 — documented as additive).

**Estimated prompt size:** ~440 lines.

**Prompt file:** [`tasks/WP05-entity-destination.md`](./tasks/WP05-entity-destination.md)

---

### WP06 — CLI runner: import:run + run-all + status + dry-run

**Goal:** Compose the substrate into a working runner. Ship `MigrationRunner` + three CLI commands.

**Priority:** p1

**Success criteria:** Exit codes match spec §9.1; dry-run does not write; per-record errors captured in `RunReport.errors` (capped at 100); run-level errors halt regardless of `--halt-on-error`.

**Subtasks**
- [x] T033 RunOptions + RunReport value objects (WP06)
- [x] T034 ProcessChainExecutor (WP06)
- [x] T035 MigrationRunner + MigrationAbortedException (WP06)
- [x] T036 ImportRunCommand (WP06)
- [x] T037 ImportRunAllCommand (WP06)
- [x] T038 ImportStatusCommand (WP06)

**Implementation sketch:** `MigrationRunner` is constructor-injected with `MigrationRegistry`, `ProcessChainExecutor`, `MigrationIdMap`, `LoggerInterface`. Per-record errors use a separate `try/catch` from run-level errors (FR-046 vs FR-048). `ImportStatusCommand` shows `0` for failed/skipped until WP07 wires in the run-state.

**Parallel opportunities:** T033, T034, T037, T038 are independent.

**Dependencies:** WP01, WP02, WP05.

**Owned files:** see `tasks/WP06-cli-runner.md` frontmatter (13 entries — also touches `packages/cli/src/Command/Import/` for the three commands).

**Risks:** CLI base-class drift in `packages/cli/` (R1 — mitigated by reading the live surface at implementation time); generator + try/catch interaction (R2 — covered by mid-iteration crash test); UUIDv7 dependency on `symfony/uid` (R3 — likely already present).

**Estimated prompt size:** ~390 lines.

**Prompt file:** [`tasks/WP06-cli-runner.md`](./tasks/WP06-cli-runner.md)

---

### WP07 — Resume + progress tracking

**Goal:** Persist per-record progress and wire `import:resume`. Refines `import:status` to show real failed/skipped counts.

**Priority:** p1

**Success criteria:** Interrupt-then-resume reuses the same `run_id`; full resume cycle round-trips against a 100-record fixture; `migration_run_state` is documented as mission-internal (not §5.8 stable surface).

**Subtasks**
- [x] T039 migration_run_state schema + migration file (WP07)
- [x] T040 MigrationRunState repository (WP07)
- [x] T041 Extend MigrationRunner with progress + runResume() (WP07)
- [x] T042 ImportResumeCommand (WP07)
- [x] T043 Integration test: full resume flow (WP07)

**Implementation sketch:** Column is `item_status` (not `status`) per the data-model.md §4.2 note on reserved identifiers. `MigrationRunner` accepts a nullable `MigrationRunState` to preserve WP06's test scaffolding. Per-record commit by default; batch mode (≤ 100 records) deferred to a future flag.

**Parallel opportunities:** T040 + T042 (after T039 lands).

**Dependencies:** WP04, WP06.

**Owned files:** see `tasks/WP07-resume-progress.md` frontmatter (7 entries). Schema directory split with WP04 — explicit per-file ownership.

**Risks:** Concurrent CLI invocations corrupt `position` (R1 — prevented by WP09's lock); per-record commits slow on large migrations (R2 — batch-mode lever deferred); `run_id` collision (R3 — UUIDv7 negligible probability).

**Estimated prompt size:** ~310 lines.

**Prompt file:** [`tasks/WP07-resume-progress.md`](./tasks/WP07-resume-progress.md)

---

### WP08 — Rollback

**Goal:** Ship `RollbackWalker` + `import:rollback` + `import:reset`. Best-effort per-record semantics.

**Priority:** p1

**Success criteria:** Walker traverses id-map in reverse-creation order; per-record failures logged on `entity.lifecycle` without halting walk; `import:reset` does NOT delete destination entities.

**Subtasks**
- [x] T044 RollbackReport value object (WP08)
- [x] T045 RollbackWalker (WP08)
- [x] T046 ImportRollbackCommand (WP08)
- [x] T047 ImportResetCommand (WP08)
- [x] T048 Integration test: full rollback flow (WP08)

**Implementation sketch:** `RollbackWalker` consumes `MigrationIdMap::walkReverseCreation()` (the lazy generator from WP04). Both CLI commands require `--confirm` (destructive-op gate). `import:reset` clears id-map + run-state but never touches entities.

**Parallel opportunities:** T044, T047 are independent.

**Dependencies:** WP04, WP05.

**Owned files:** see `tasks/WP08-rollback.md` frontmatter (8 entries).

**Risks:** Reverse-creation order on tied timestamps (R1 — handled by WP04's secondary sort); cross-migration rollback (R2 — documented operator concern); rollback during concurrent run (R3 — prevented by WP09's lock); `--confirm` foot-gun (R4 — documented).

**Estimated prompt size:** ~330 lines.

**Prompt file:** [`tasks/WP08-rollback.md`](./tasks/WP08-rollback.md)

---

### WP09 — Concurrency lock + MigrationConcurrencyException

**Goal:** Filesystem lock prevents concurrent `import:*` invocations against the same migration. `MigrationConcurrencyException` carries operator-actionable payload.

**Priority:** p1

**Success criteria:** Lock file path matches spec §9.3; PID inside the file; pcntl-based graceful release on SIGTERM/SIGINT; stale-lock recovery documented.

**Subtasks**
- [x] T049 MigrationLock class (WP09)
- [x] T050 MigrationConcurrencyException (WP09)
- [x] T051 Wire lock into all mutating CLI commands (WP09)
- [x] T052 Integration test: concurrent acquisition + signal handling (WP09)

**Implementation sketch:** `flock($handle, LOCK_EX | LOCK_NB)`. Lock dir at `storage/migration-locks/`. `pcntl_signal()` for SIGTERM/SIGINT release when available; `register_shutdown_function` fallback. Windows degrades gracefully. `import:status` does NOT acquire a lock (read-only).

**Parallel opportunities:** T050 is independent.

**Dependencies:** WP06.

**Owned files:** see `tasks/WP09-concurrency-lock.md` frontmatter (4 entries). T051 modifies CLI command files (WP06-WP08 owned); the modification is additive (wraps `execute()` body in try/finally).

**Risks:** `flock()` on NFS unreliable (R1 — documented; framework requires local FS); signal handler delivery delays (R2 — `pcntl_async_signals(true)` + shutdown-function fallback); Windows lacks graceful shutdown (R3 — degraded); PID reuse race (R4 — operators verify before deleting).

**Estimated prompt size:** ~290 lines.

**Prompt file:** [`tasks/WP09-concurrency-lock.md`](./tasks/WP09-concurrency-lock.md)

---

### WP10 — Conformance suite + reference CsvSource fixture

**Goal:** Ship the two abstract test bases (`SourceConformanceTestCase`, `DestinationConformanceTestCase`) under `testing/` (autoload-dev only). Ship the reference `CsvSource` fixture and the two concrete tests.

**Priority:** p1

**Success criteria:** Both bases are reusable from third-party packages; reference conformance tests green; `composer install --no-dev` does NOT install the test bases.

**Subtasks**
- [x] T053 CsvSource reference fixture (WP10)
- [x] T054 SourceConformanceTestCase abstract base (WP10)
- [x] T055 DestinationConformanceTestCase abstract base (WP10)
- [x] T056 ReferenceSourceConformanceTest (WP10)
- [x] T057 ReferenceDestinationConformanceTest (WP10)

**Implementation sketch:** `testing/` directory is `autoload-dev` only (CLAUDE.md gotcha). Source base has eight gates (C1–C8) covering laziness, determinism, hash stability, memory bounds. Destination base has seven gates (D1–D7). Reference tests subclass and pass.

**Parallel opportunities:** T054, T055 are independent.

**Dependencies:** WP01, WP05.

**Owned files:** see `tasks/WP10-conformance-suite.md` frontmatter (7 entries).

**Risks:** Memory assertion brittle on CI (R1 — generous threshold); autoload-dev regression (R2 — covered by `composer install --no-dev` smoke test in DoD); conformance suite drift from spec (R3 — FR references in PHPDoc); large CSV generation slow in CI (R4 — ~5 seconds, acceptable).

**Estimated prompt size:** ~340 lines.

**Prompt file:** [`tasks/WP10-conformance-suite.md`](./tasks/WP10-conformance-suite.md)

---

### WP11 — End-to-end validation: CSV → entity with resume + rollback

**Goal:** A 1000-record CSV → entity round-trip with resume + rollback proven in a single integration test. The mission's acceptance gate.

**Priority:** p1

**Success criteria:** Full import + resume cycle + rollback + idempotent re-run + operator-path-via-CommandTester all green in one test class.

**Subtasks**
- [x] T058 users-1000.csv fixture (WP11)
- [x] T059 UsersCsvToWidgetsMigration definition fixture (WP11)
- [x] T060 Integration test: full E2E (WP11)
- [x] T061 Operator-path E2E via CommandTester (WP11)

**Implementation sketch:** 1000-row CSV committed (~250 KB). The migration exercises every process-plugin shape: string shorthand, single processor, chain, default value. Five `#[Test]` methods in one class cover FR-053..FR-055 plus idempotency and operator-path.

**Parallel opportunities:** T058, T059 are independent.

**Dependencies:** WP06, WP07, WP08, WP10.

**Owned files:** see `tasks/WP11-end-to-end-validation.md` frontmatter (3 entries).

**Risks:** Slow test on cold cache (R1 — budget 60 seconds); memory leak in long test (R2 — asserted in tearDown); event subscriber bleed (R3 — per-test reset); `--limit` semantics on resume (R4 — explicitly verified); run-state survives rollback (R5 — documented behavior).

**Estimated prompt size:** ~310 lines.

**Prompt file:** [`tasks/WP11-end-to-end-validation.md`](./tasks/WP11-end-to-end-validation.md)

---

### WP12 — Documentation + charter §5.8 amendment

**Goal:** Close the mission with the canonical spec, two author guides, the cookbook, the upgrade-guide entry, and the charter §5.8 amendment + CLAUDE.md integration.

**Priority:** p1

**Success criteria:** All five documentation FRs (FR-056..FR-060) land; charter §5.8 covers every spec §4 symbol; CLAUDE.md orchestration table includes `packages/migration/*` row.

**Subtasks**
- [ ] T062 docs/specs/migration-platform.md canonical spec (WP12)
- [ ] T063 docs/extension-authoring/migration-source-readers.md (WP12)
- [ ] T064 docs/extension-authoring/migration-process-plugins.md (WP12)
- [ ] T065 docs/cookbook/migration-first-cut.md (WP12)
- [ ] T066 docs/upgrades/waaseyaa-alpha-X-to-Y.md (WP12)
- [ ] T067 Charter §5.8 + CLAUDE.md + public-surface-map (WP12)

**Implementation sketch:** All edits are markdown — `execution_mode: planning_artifact`. No file under `packages/` is modified. Alpha range in T066's filename is determined by `git describe --tags --abbrev=0` at implementation time.

**Parallel opportunities:** T062..T066 are independent.

**Dependencies:** WP04, WP06, WP09 (accurate docs require these merged; in practice this WP runs last after WP11 closes).

**Owned files:** see `tasks/WP12-docs-charter-amendment.md` frontmatter (8 entries).

**Risks:** Alpha version drift between planning and merge (R1 — re-verify at PR-up); charter §5.8 numbering collision (R2 — check at merge); sample code bit-rot (R3 — copy from WP11 fixture); public-surface-map missing (R4 — skip file if absent).

**Estimated prompt size:** ~360 lines.

**Prompt file:** [`tasks/WP12-docs-charter-amendment.md`](./tasks/WP12-docs-charter-amendment.md)

---

## Cross-cutting modifications

The following files are owned by other WPs / packages but get additive, well-scoped modifications during this mission. Each is documented in the touching WP's prose; none appear in `owned_files` to avoid ownership conflicts.

### `packages/entity-storage/src/SaveContext.php` (M-001-owned)

- **Touched by:** WP05 / T028.
- **Change:** Add `public readonly bool $isImport = false` constructor parameter. Additive only — existing call sites preserved by default value.
- **Charter impact:** Extends §5.3 (Entity surface) by one method. Documented in WP12's §5.8 amendment with a cross-link to §5.3.

### `packages/cli/src/Command/Import/Import*Command.php` (WP06 / WP07 / WP08 owned)

- **Touched by:** WP09 / T051.
- **Change:** Wrap `execute()` body in `try/finally` around `MigrationLock::acquire()` / `release()`. Catch `MigrationConcurrencyException` → exit 2.
- **Charter impact:** None. Internal mechanics.

### `packages/migration/src/MigrationIdMap.php` (WP04-owned)

- **Touched by:** WP05 / T029 (`EntityDestination::rollback()` needs `deleteByHash()`).
- **Change:** Add `deleteByHash(string $migrationId, string $sourceIdHash): bool`. Additive method, `@api`-tier.
- **Charter impact:** Extends `MigrationIdMap`'s API surface (still in §5.8 boundary).

### `packages/migration/src/Runner/MigrationRunner.php` (WP06-owned)

- **Touched by:** WP07 / T041 (`runResume()` + per-record progress writes).
- **Change:** Add `runResume()` method and `MigrationRunState` collaborator (nullable for backward compatibility).
- **Charter impact:** Extends the runner API; `runResume()` is on §5.8 stable surface.

### `CLAUDE.md` (project root) — orchestration table + Layer 3 row

- **Touched by:** WP12 / T067.
- **Change:** Add `packages/migration/*` row + insert `migration` into Layer 3 services list.
- **Charter impact:** Doctrine artifact; not §5.8 surface but tightly coupled to it.

### `docs/specs/stability-charter.md` — charter §5.8 amendment

- **Touched by:** WP12 / T067.
- **Change:** Add §5.8 "Migration platform" section listing every spec §4 stable-surface symbol.
- **Charter impact:** This IS the charter amendment.

### `composer.json` (project root)

- **Touched by:** WP01 / T001.
- **Change:** Add `packages/migration` path-repository entry + `"waaseyaa/migration": "self.version"` require entry.
- **Charter impact:** Governed by `bin/check-composer-policy` (CP001, CP002, CP003, CP006, CP-NEW).

---

## Parallelization map

```
                          ┌──────────────────────────────────────┐
                          │ M-001 (entity-storage-v2): MERGED    │
                          │ commit 0f7e1809a — prereq for WP05   │
                          └──────────────────┬───────────────────┘
                                             ▼
WP01 ──┬──► WP02 ─────────────┐
       │                       │
       ├──► WP03               │
       │                       │
       ├──► WP04 ────► WP05 ──►│──► WP06 ──┬──► WP07
       │                       │            │
       ├──► WP10 ──────────────┘            ├──► WP09
                                            │
                                            └──► WP08 ──┐
                                                        │
                              WP06+WP07+WP08+WP10 ──────┴──► WP11 ──► WP12
```

**Layered execution plan:**

1. **Layer 0 (immediate):** WP01.
2. **Layer 1 (after WP01 merges):** WP02, WP03, WP04, WP10 — parallel.
3. **Layer 2 (after WP01 + WP04 merge):** WP05.
4. **Layer 3 (after WP01 + WP02 + WP05 merge):** WP06.
5. **Layer 4 (after WP06 merges):** WP07, WP08, WP09 — parallel.
6. **Layer 5 (after WP06 + WP07 + WP08 + WP10 merge):** WP11.
7. **Layer 6 (after WP04 + WP06 + WP09 merge — typically last):** WP12.

**Critical path:** WP01 → WP04 → WP05 → WP06 → WP07 → WP11 → WP12 (7 WPs serially, ~3.5 weeks at 4-day WP cycles).

**Maximum parallelism:** After WP01 lands, four lanes run concurrently (WP02, WP03, WP04, WP10). After WP06 lands, three lanes run concurrently (WP07, WP08, WP09).

---

## MVP cut

The smallest publishable substrate is **WP01 + WP02 + WP04 + WP10**:

- WP01 — plugin contracts.
- WP02 — manifest format + registry.
- WP04 — id-map + idempotency primitives.
- WP10 — conformance suite.

After these four merge, the framework exposes plugin contracts and a conformance suite — enough for a third party to ship a source-reader package and prove it passes. **NOT recommended as a real release cut** because without WP05 (`EntityDestination`) and WP06 (CLI), the platform is framework-internal use only. Documented to clarify the dependency floor.

The full mission ship cut is **all 12 WPs**.

---

## Notes for the implementing agents

- Run `composer dump-autoload --optimize` after WP01 lands; subsequent WPs assume the optimized classmap.
- `composer cs-check` may need a second pass with cleared cache (`feedback_cs_fix_two_passes.md`).
- `./vendor/bin/phpunit` runs the FULL suite, not just the changed package — M-006 reviewer lesson.
- All `@spec FR-xxx` annotations are reverse-resolvable by `rg`. Verify before review-handoff.
- Status checks: `bin/check-package-layers`, `bin/check-composer-policy`, `bin/audit-dead-code`.
- WP boundary: never modify files outside `owned_files` without documenting the cross-cutting touch in the WP's prose.
- Per-WP worktree branches are computed at finalize-tasks time. The agent enters them via `spec-kitty agent action implement <WPxx> --agent opus`.
- Reviewer is also opus. Rejection escalation: N=2 → user-as-arbiter.

---

_Generated by `/spec-kitty.tasks` for mission M-002 `migration-platform-v1-01KRCDE9`. Do not edit directly; `finalize-tasks` reads `wps.yaml` and resyncs this surface. The prompt files under `tasks/WPxx-*.md` are the authoritative implementer briefs._
