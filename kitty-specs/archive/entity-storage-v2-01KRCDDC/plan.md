# Implementation Plan: Entity Storage v2 — Multi-Backend Storage with Revisions

**Branch**: `kitty/mission-entity-storage-v2-01KRCDDC`
**Date**: 2026-05-11
**Mission**: `entity-storage-v2-01KRCDDC` (M-001)
**Spec**: [spec.md](spec.md) (canonical doctrine at `docs/specs/entity-storage-v2.md`)
**Governing ADRs**: 010 (multi-backend field storage), 011 (entity lifecycle events), 016 (revisions first-class)
**Charter governance**: §5.3 (governs), §3.2.7 + §3.2.8 (beta-gate contributors)

---

## Summary

Lift the single-backend `_data` JSON assumption in `packages/entity-storage/` into a stable `FieldStorageBackendInterface` contract with two concrete backends (`sql-blob` refactor, `sql-column` new) and a storage coordinator that fans reads/writes across registered backends, dispatches the four ADR-011 lifecycle events, and supports first-class revisions per ADR 016. Validate by migrating Minoo's `teaching` entity end-to-end. Twelve work packages.

Approach (from research §1–§2 and spec §3–§13):

1. Establish the backend contract and registration mechanism (WP01).
2. Build the coordinator skeleton without events (WP02), then refactor the current `_data` path into `sql-blob` with behavior-identity tests (WP03).
3. Layer in lifecycle events and `PartialSaveException` (WP04) in parallel with the new `sql-column` backend (WP05).
4. Wire query support at definition-validation time (WP06) and design the revision schema co-designed with `sql-column` (WP07).
5. Implement revision storage (WP08), per-revision access (WP09), and the migration generator CLI (WP10) — WP09 and WP10 are parallelizable.
6. Validate the substrate by migrating `teaching` (WP11) and close with the conformance suite + docs (WP12).

---

## Technical Context

**Language/Version**: PHP 8.5+ (project-mandated minimum); TypeScript only for the admin SPA (out of scope for this mission). Strict types in every file.

**Primary Dependencies**:
- Doctrine DBAL (canonical SQL abstraction — `Waaseyaa\Foundation\Database\DBALDatabase`).
- Symfony EventDispatcher (lifecycle events).
- Symfony Console (migration generator CLI).
- Composer-based provider discovery (`HasFieldStorageBackendsInterface` follows existing `HasNativeCommandsInterface` pattern).

**Storage**: SQLite for dev/CI (`DBALDatabase::createSqlite()` — in-memory in tests); Postgres and SQLite supported in production. Type mapping in spec §8.2 covers both.

**Testing**:
- PHPUnit 10.5 (project-mandated; no `-v` flag).
- Contract-test base classes for backend-conformance suite (per `.claude/rules/feedback_modern_php_rules.md`).
- Pest is NOT used in this project.
- Existing in-memory test fixtures (`InMemoryEntityStorage`, `DBALDatabase::createSqlite()`).
- Integration tests under `tests/Integration/PhaseN/` per project convention.

**Target Platform**: PHP-FPM behind Caddy; CLI via `bin/waaseyaa`. Multi-tenant aware via existing `ConnectionResolverInterface`.

**Project Type**: Single project (PHP monorepo). Affected packages all live in Layer 0/1 (Foundation, Core Data) plus the Layer 6 CLI entry point.

**Performance Goals** (substrate-relevant):
- `sql-blob` refactor: zero observable behavior regression (FR-008; behavior-identity test gate).
- WP11 validation entity migration completes in <60s on production-sized dataset (spec §15, §10).
- Coordinator overhead per write: <5ms p95 for single-backend writes (target; not in spec, but measurable; refine in WP02 if needed).

**Constraints**:
- Charter §5.3 stable surface — every public symbol named in spec §4 is a contract; breaking changes require charter amendment.
- Layer rule: `entity-storage`/`entity`/`field`/`access` are Layer 1; CLI command lives in Layer 6 (`cli` package). No upward imports introduced.
- No `psr/log` — use `Waaseyaa\Foundation\Log\LoggerInterface`.
- No service locators or class-string registries (per `.claude/rules/feedback_modern_php_rules.md`); reflection-discovered surfaces must be marked `@api`.
- No upward imports from Foundation/Core Data into higher layers.

**Scale/Scope**:
- 12 work packages.
- ~57 functional requirements (spec §3).
- Public-surface deliverables: 5 interfaces, 4 event classes, 5 exception classes, 1 value object, 2 constants/op tokens, 1 CLI command, 1 new entity-type slot, 2 new `FieldDefinition` methods.
- One downstream Minoo entity migration (validation gate, ~50 fields).
- Two downstream missions unblocked (M-002 WP05, M-004).

---

## Charter Check

**Charter file**: `docs/specs/stability-charter.md` (present in repo).
**Mission filing readiness**: All gates pass per `mission.json.filing_readiness` (spec_complete=true, adrs_accepted=true, charter_governs=true, agent_assignments_resolved=true, external_dependencies_satisfiable=true, ready=true).

| Gate | Status | Evidence |
|---|---|---|
| Stable surface enumerated | PASS | Spec §4 lists every charter §5.3 entry the mission delivers. |
| Governing ADRs accepted | PASS | ADRs 010, 011, 016 are merged + Accepted (see `docs/adr/`). |
| Charter sections governing this mission named | PASS | §5.3 (governs), §3.2.7, §3.2.8 (beta-gate). |
| External dependencies satisfiable | PASS | `mission.json.external_dependencies: []`. |
| Validation consumer identified | PASS | Minoo + `teaching` entity (spec §15, mission.json). |
| Agent assignments resolved | PASS | implementer=sonnet, reviewer=opus, escalation=opus-as-implementer after N=2 rejections (mission.json). |
| No breaking changes introduced (alpha-stable surface) | PASS | All additions are additive: `revisionable` defaults false, `primaryStorageBackend` defaults `sql-blob`, `storedIn()` opt-in, `view_revision` falls back to `view`. (Spec §1.2, §2.2, §11.2.) |
| Non-goals enumerated | PASS | Spec §1.2 + §2.2 (moderation, per-field translation, revision UI, vector impl, remote backend, cross-backend joins, auto-pruning, listing UI, mass Minoo migration). |
| Layer-architecture compliance | PASS | All new code stays in Layer 1 (`entity-storage`, `entity`, `field`, `access`) and Layer 6 (`cli` for the generator command). No upward edges. |

**Re-check trigger**: After Phase 1 (data-model, contracts) — repeat this table; flag any new gaps to the user.

---

## Project Structure

### Documentation (this feature)

```
kitty-specs/entity-storage-v2-01KRCDDC/
├── spec.md                     # Canonical doctrine spec (mirror of docs/specs/entity-storage-v2.md)
├── plan.md                     # THIS FILE
├── research.md                 # Phase 0 — decisions, rationale, risk register (created)
├── data-model.md               # Phase 1 — stable-surface symbols, storage shape, lifecycle/access semantics (created)
├── quickstart.md               # Phase 1 — operator + integrator quickstart (TO CREATE)
├── contracts/                  # Phase 1 — interface signatures + event payloads (TO CREATE)
│   ├── field-storage-backend.md
│   ├── lifecycle-events.md
│   ├── revisionable-entity.md
│   ├── partial-save-error.md
│   └── migration-generator-cli.md
├── checklists/
│   └── requirements.md         # Created during specify
├── research/
│   ├── source-register.csv     # Phase 0
│   └── evidence-log.csv        # Phase 0
├── tasks/                      # Phase 2 — created by /spec-kitty.tasks (DO NOT pre-populate)
├── meta.json
├── mission-events.jsonl
└── status.events.jsonl
```

### Source Code (repository)

Single-project monorepo. Affected paths only:

```
packages/entity-storage/                      # Layer 1 — primary surface for this mission
├── src/
│   ├── Backend/
│   │   ├── FieldStorageBackendInterface.php   # NEW (WP01)
│   │   ├── HasFieldStorageBackendsInterface.php  # NEW (WP01)
│   │   ├── SqlBlobBackend.php                 # NEW (WP03 — refactor of _data path)
│   │   └── SqlColumnBackend.php               # NEW (WP05)
│   ├── Event/
│   │   ├── EntityLifecycleEventInterface.php  # NEW (WP04)
│   │   ├── BeforeSaveEvent.php                # NEW (WP04)
│   │   ├── AfterSaveEvent.php                 # NEW (WP04)
│   │   ├── BeforeDeleteEvent.php              # NEW (WP04)
│   │   ├── AfterDeleteEvent.php               # NEW (WP04)
│   │   └── AbortOperationException.php        # NEW (WP04)
│   ├── Exception/
│   │   ├── PartialSaveException.php           # NEW (WP04)
│   │   ├── UnsupportedQueryException.php      # NEW (WP06)
│   │   ├── UnsupportedListingException.php    # NEW (WP06, reserved)
│   │   └── BackendIdCollisionException.php    # NEW (WP01)
│   ├── EntityStorageCoordinator.php           # NEW (WP02; events added WP04)
│   ├── SaveContext.php                        # NEW (WP04)
│   ├── RevisionableEntityStorageInterface.php # NEW (WP08)
│   ├── RevisionPruner.php                     # NEW (WP08; ships disabled)
│   ├── SqlEntityStorage.php                   # MODIFIED (WP03 — extracted into SqlBlobBackend)
│   ├── SqlSchemaHandler.php                   # MODIFIED (WP03/WP05/WP07 — split blob vs column generation)
│   ├── EntityRepository.php                   # MODIFIED (uses coordinator; preserves canonical pipeline)
│   ├── EntityStorageFactory.php               # MODIFIED (binds coordinator + backends)
│   └── (existing files unchanged)
└── tests/
    ├── Contract/Backend/                      # NEW (WP12 — backend-conformance suite)
    ├── Integration/Coordinator/               # NEW (WP02, WP04)
    ├── Integration/Revisions/                 # NEW (WP08, WP09)
    └── Integration/BehaviorIdentity/          # NEW (WP03 — snapshot tests)

packages/entity/                              # Layer 1 — entity type + revision interface
├── src/
│   ├── EntityType.php                         # MODIFIED (WP07 — add revisionable, primaryStorageBackend)
│   ├── RevisionableEntityInterface.php        # NEW (WP07)
│   ├── RevisionableEntityTrait.php            # MODIFIED (WP08 — load/save revision integration)
│   └── (existing files unchanged)
└── tests/

packages/field/                               # Layer 1 — FieldDefinition API surface
├── src/
│   ├── FieldDefinition.php                    # MODIFIED (WP01 — add storedIn(), indexed())
│   └── (existing files unchanged)
└── tests/

packages/access/                              # Layer 1 — per-revision access op
├── src/
│   ├── Gate/GateInterface.php                 # MODIFIED (WP09 — add view_revision op constant)
│   ├── Gate/PolicyAttribute.php               # (existing — operations array accepts view_revision)
│   └── (existing files unchanged)
└── tests/

packages/cli/                                 # Layer 6 — generator command
├── src/Command/
│   └── MakeStorageMigrationCommand.php        # NEW (WP10)
└── tests/

docs/                                          # WP12
├── specs/entity-system.md                     # MODIFIED (WP12 — backend section)
├── specs/field-storage-backends.md            # NEW (WP12)
└── upgrades/waaseyaa-alpha-<X>-to-<Y>.md      # NEW (WP12 — first upgrade-guide entry)

minoo (downstream validation app)             # WP11 — outside this repo
└── (teaching entity migrated end-to-end)
```

**Structure Decision**: Single project (no frontend/backend split). All mission code targets PHP packages in `packages/` plus docs.

---

## Phase 0 — Outline & Research

**Status**: COMPLETE.

Artifacts:
- `kitty-specs/entity-storage-v2-01KRCDDC/research.md` — 11 decisions (D1–D11) anchored to spec §1–§17, 6 open-question recommendations (Q1–Q6), 7-risk register, sequencing summary, downstream consumers, scope fence, acceptance restatement.
- `kitty-specs/entity-storage-v2-01KRCDDC/research/source-register.csv` — 20 sources (spec, ADRs 009/010/011/016, charter, audit, downstream missions, current code, project rules).
- `kitty-specs/entity-storage-v2-01KRCDDC/research/evidence-log.csv` — 39 findings tied to decisions/open-questions/scope-fence with source quotes.

**Unresolved clarifications**: none. All six open questions from spec §16 have recommended resolutions in research §3, each anchored to a specific WP for adoption.

---

## Phase 1 — Design & Contracts

**Status**: IN PROGRESS (data-model.md complete; contracts/ and quickstart.md pending).

### 1.1 Entity model

See `data-model.md` (created). Stable-surface symbols, entity-type additive shape, sql-blob vs sql-column storage shape, lifecycle/revision/access semantics, layering check, charter anchors.

### 1.2 Contracts to generate

The following contract documents will live under `contracts/` and serve as the normative interface specs each WP must implement:

| Contract file | Owning WP(s) | Content |
|---|---|---|
| `contracts/field-storage-backend.md` | WP01, WP03, WP05 | `FieldStorageBackendInterface` signature + `HasFieldStorageBackendsInterface` capability + reserved-id constant. |
| `contracts/lifecycle-events.md` | WP04 | `EntityLifecycleEventInterface` + four event classes + `AbortOperationException` + `entity.lifecycle` log channel. |
| `contracts/revisionable-entity.md` | WP07, WP08 | `RevisionableEntityInterface` + `RevisionableEntityStorageInterface` + `SaveContext` + revision schema shape. |
| `contracts/partial-save-error.md` | WP04 | `PartialSaveException` payload + recovery contract. |
| `contracts/migration-generator-cli.md` | WP10 | `bin/waaseyaa make:storage-migration` CLI contract + emitted migration shape. |

These are generated below.

### 1.3 Quickstart

`quickstart.md` (created in this step) gives:
- A migrator-eye view of taking one entity type from `sql-blob` to `sql-column` with revisions enabled.
- A backend-implementer-eye view of registering a new backend (e.g. `minoo-elasticsearch`).
- A policy-author-eye view of declaring `view_revision`.

---

## Post-Phase-1 Charter Re-check

| Gate | Status | Evidence |
|---|---|---|
| New gaps surfaced during design | NONE | All Phase 1 artifacts trace back to spec §3 FRs; no requirements added beyond the ratified spec. |
| Stable-surface deltas reflect spec §4 | PASS | data-model.md §1.1–§1.5 mirrors spec §4. |
| Layer rule compliance | PASS | Project-structure tree introduces no upward edges. |
| Non-goal creep | PASS | Contracts are scoped to in-scope §2.1 items only. No moderation, translation, admin UI, vector impl, remote backend, cross-backend joins, auto-pruning, listing UI, or mass-migration content. |

---

## Phase 2 — Task generation

**NOT EXECUTED BY THIS COMMAND.** Run `/spec-kitty.tasks` to materialize work packages from spec §13 (12 WPs).

---

## Complexity Tracking

| Source of complexity | Location | Mitigation |
|---|---|---|
| Coordinator fan-out logic across backends | `packages/entity-storage/src/EntityStorageCoordinator.php` | Keep routing-only in coordinator; push backend-specific logic into backend classes. Acceptance: coordinator never reads field-level data, only routes. (Research risk #2.) |
| `sql-blob` refactor must be byte-identical to existing behavior | `packages/entity-storage/src/Backend/SqlBlobBackend.php` + `SqlSchemaHandler.php` | WP03 behavior-identity test suite is a hard gate. Snapshot every observable behavior of `SqlEntityStorage` BEFORE refactor; compare AFTER. (Research risk #1.) |
| Partial-save semantics across heterogeneous backends | `PartialSaveException` + coordinator §6.5 contract | Explicit failure (no silent retry). Document recovery patterns in WP12 `entity-system.md` update. Integration test in WP11 exercises the failure mode. (Research risk #3.) |
| Revision write amplification on `sql-blob` opt-ins | `packages/entity-storage/src/SqlBlobBackend.php` revision path | Revision tables are explicit opt-in; entity types that do not need history pay nothing. Document trade-off in upgrade-guide. (Research risk #4.) |
| Migration generator emitting unsafe migrations for large entity types | `packages/cli/src/Command/MakeStorageMigrationCommand.php` | FR-043 reversibility default + `expectedReverseSeconds` docblock annotation warning. WP11 validates with `teaching` (small dataset) first. (Research risk #5, Q3.) |
| `view_revision` fallback surprising legacy policies | `packages/access/src/Gate/GateInterface.php` + GateRouter | Document fallback rule prominently in `entity-system.md` and `access-control.md`. Emit a structured log line on the `entity.lifecycle` channel when fallback fires. (Research risk #6.) |

---

## Stop point

This plan ends after Phase 1 (contracts + quickstart will be generated next). Task generation is deferred to `/spec-kitty.tasks` per the prompt's mandatory stop point.
