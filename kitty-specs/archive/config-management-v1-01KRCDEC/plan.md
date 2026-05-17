# Implementation Plan: Configuration Management v1

**Branch:** `main` (planning-and-merge target)
**Date:** 2026-05-16
**Spec:** [`spec.md`](spec.md)
**Doctrine spec:** [`docs/specs/config-management-v1.md`](../../docs/specs/config-management-v1.md)
**Governing ADR:** [`docs/adr/018-configuration-management-sync.md`](../../docs/adr/018-configuration-management-sync.md) (Accepted 2026-05-11)
**Mission ID:** M-003 (display) / `01KRCDEC7V1843QZ61CY0PH842` (Spec Kitty)
**Slug:** `config-management-v1-01KRCDEC`

**Branch contract:**
- `current_branch`: `main`
- `planning_base_branch`: `main`
- `merge_target_branch`: `main`
- `branch_matches_target`: `true`

## Summary

Ship the Drupal-shape Configuration Management Interface (CMI) on top of the existing `Waaseyaa\Config\ConfigEntityBase`. DB stays the runtime **active store**; a configurable filesystem path (`storage/config-sync/`, override via `config.sync_path`) becomes the **sync store**. Six CLI commands on the reserved `config:*` namespace handle export/import/diff/status/validate/reset. Config entities declare dependencies via a new stable `ConfigDependencyInterface`; the framework computes a topological DAG at import time, with cycle-detection and missing-dependency exceptions carrying the offending path/id. Sync files use a stable YAML format with a `_meta` block (`entity_type`, `uuid`, `dependencies`, `langcode`) and field values keyed alphabetically. Backend restriction is enforced at boot — config entities are forbidden from `vector` and `remote` backends and confined to `sql-blob` / `sql-column`. Validation reuses the existing `FieldDefinition::validators()` pipeline. A new `config.audit` log channel records every import/export/reset operation. Charter §5.5 is amended to cover the new stable surface. The mission ships 11 WPs; WP01–WP09 implement the surface, WP10 validates end-to-end via a Minoo round-trip + cycle fixture, WP11 ships documentation and the charter amendment.

## Technical Context

| Field | Value |
|---|---|
| **Language / Version** | PHP 8.5+ (project minimum; `declare(strict_types=1)` mandatory in every new file) |
| **Primary Dependencies** | Symfony 7.x (Console, Yaml, EventDispatcher, Uid, Validator), `waaseyaa/foundation` (LoggerInterface, KernelInterface, RequestContext), `waaseyaa/entity` + `waaseyaa/entity-storage` (config entity registry + storage backends), `waaseyaa/field` (`FieldDefinition::validators()` pipeline per ADR 013), `waaseyaa/typed-data` (scalar coercion for YAML round-trip), `waaseyaa/cli` (CLI kernel, `CommandDefinition`). |
| **Storage** | None new at the persistence layer. Active store is the existing `ConfigEntityBase` storage (currently `sql-blob`; opt-in `sql-column` arrives via entity-storage-v2 without changes to CMI). Sync store is filesystem only — YAML files under `storage/config-sync/`. |
| **Testing** | PHPUnit 10.5 (no `-v`; rejected at this version). Contract tests with `#[CoversNothing]`; one abstract `TestCase` per public surface, concrete subclass per backend / fixture as appropriate. Integration tests live in `tests/Integration/PhaseN/` (next phase number verified at task-outline time). WP10 exercises a Minoo round-trip plus a cycle-fixture test. |
| **Target Platform** | PHP CLI (operator-driven `bin/waaseyaa config:*` invocations + CI deploy gate); PHP-FPM under Caddy in production for boot-time backend validation. Linux x86_64 / WSL2 dev. |
| **Project Type** | Monorepo PHP framework. Touched layers: **L1** (additions to `packages/config`); **L6** (new `bin/waaseyaa config:*` command set in `packages/cli`). Layer discipline enforced by `bin/check-package-layers`. The config package may not reach upward beyond L1; CLI command classes live in `packages/cli` (L6) and depend on L1 services. |
| **Performance Goals** | NFR-C1 export of 200 entities completes in < 1 s on a Sonnet-class machine. NFR-C2 import dependency-graph computation is O(V+E) and < 100 ms for 200 nodes / 400 edges. NFR-C3 zero new PHPStan / PHPUnit warnings (level 5). NFR-C4 deterministic YAML output (sorted keys, identical export → no spurious diff on second export). |
| **Constraints** | C-001 layer graph: `config` (L1) → `entity-storage` (L1) → `foundation` (L0). No upward edges. CLI commands live at L6. C-002 no schema migration of existing config entities — additive only. C-003 backend restriction enforced at boot (typed exception, not silent). C-004 `config:*` verb namespace reserved framework-side; apps registering reserved verbs fail at boot. C-005 sync-store YAML format is on stable surface (charter §5.5); changes follow charter §4 deprecation cycle. C-006 per-entity transactions (FR-023); not full-import atomicity. |
| **Scale / Scope** | 61 FRs (`spec.md` §3), 6 stable-surface deliverables (§4), 11 WPs (§8). Touched files: `packages/config/src/` (new `Sync/`, `Dependency/`, `Audit/` sub-namespaces; additive `Exception/` entries), `packages/cli/src/Command/Config/` (NEW directory; six command classes + base class), `packages/entity-storage/src/StorageBackendRegistry.php` (boot-time backend-restriction hook), `docs/specs/config-management.md` (NEW post-mission), `docs/specs/stability-charter.md` (§5.5 amendment), `docs/cookbook/config-sync.md` (NEW), `docs/upgrade-notes/<alpha-train>.md` (NEW entry). |

## Charter Check

| Charter section | Gate | Status | Notes |
|---|---|---|---|
| **Testing Standards** | Contract + integration tests for new public surface. | PASS | Spec §3.12 requires Minoo round-trip + cycle fixture. Per-command contract tests + a `ConfigDependencyInterface` contract test for the no-op default. |
| **Quality Gates** | `composer phpstan` level 5, `composer cs-check`, `bin/check-package-layers`, `bin/check-composer-policy` green. | PASS | All additions in `packages/config` stay at L1; CLI commands at L6 depend on L1 contracts only. No upward edges. No composer-manifest changes that affect CP002/CP003/CP006. |
| **Performance Benchmarks** | NFR thresholds quantified. | PASS | NFR-C1 (< 1 s for 200-entity export), NFR-C2 (DAG O(V+E) < 100 ms for 200 nodes). Sentinel-only; not a CI gate. |
| **Branch Strategy** | Plan/base/merge explicit and matched. | PASS | main → main → main. `branch_matches_target = true`. |
| **DIR-001 / DIR-002 / DIR-003** | Project directives. | PASS | No mission-specific override needed. |
| **Paradigm: domain-driven-design** | Repository/value-object discipline. | PASS | `ConfigSyncFile`, `ConfigManifestEntry`, `DependencyGraph`, `DiffResult`, `StatusReport` are pure value objects; `ConfigSyncManager`, `ConfigImporter`, `ConfigExporter`, `DependencyResolver`, `ConfigDiffer` are domain services; `ConfigSyncRepository` exposes filesystem reads/writes behind an interface. |
| **Charter §5.5 (config/env)** | Amended to cover sync layer + `config:*` CLI namespace. | DEFERRED | Amendment lands in WP11. Spec §4 enumerates the new stable surface to add. |
| **Charter §3.2 criterion 9 (beta gate)** | Cleared by ADR 018 acceptance; mission ships the implementation. | PASS | Acceptance criterion landed in §3.2 already. The merge of WP10 + WP11 retires the "📋 planned" status to "✅ shipped". |

**Re-evaluation post-Phase-1:** All gates re-checked after `data-model.md` + `contracts/` generation. PASS unchanged.

## Project Structure

### Mission documentation

```
kitty-specs/config-management-v1-01KRCDEC/
├── spec.md                       # 470 lines, committed at e1e455ebf
├── plan.md                       # this file
├── research.md                   # Phase 0 — R-01..R-06 decision rationale + naming reconciliation
├── data-model.md                 # Phase 1 — value-object + service + storage shapes
├── quickstart.md                 # Phase 1 — install / declare / export / edit / import walkthrough
├── contracts/                    # Phase 1 — stable-surface contracts
│   ├── active-sync-store.md       # Dual-storage contract (ConfigStorageInterface + ConfigSyncRepository)
│   ├── config-manifest.md         # On-disk YAML shape (`_meta` block, field-value mapping)
│   ├── dependency-graph.md        # ConfigDependencyInterface, DAG ordering, cycle/missing exceptions
│   └── cli-namespace.md           # config:* command surface + collision-check semantics
├── checklists/                    # populated at tasks phase
├── meta.json
└── status.events.jsonl
```

### Source paths touched

```
packages/config/                                  # ADDITIONS at L1
├── src/
│   ├── Dependency/
│   │   ├── ConfigDependencyInterface.php          # WP01 — stable surface
│   │   ├── DependencyGraph.php                    # WP01 — value object (nodes + edges + topological order)
│   │   ├── DependencyResolver.php                 # WP01 — DFS, cycle detection, missing detection
│   │   └── Exception/
│   │       ├── ConfigDependencyCycleException.php # WP01 — carries cycle path
│   │       └── ConfigDependencyMissingException.php # WP01 — carries missing id
│   ├── Sync/
│   │   ├── ConfigSyncFile.php                     # WP02 — value object (entity_type, id, meta block, fields)
│   │   ├── ConfigSyncRepository.php               # WP02 — filesystem reads/writes under storage/config-sync/
│   │   ├── ConfigSyncSerializer.php               # WP02 — entity → YAML and YAML → array
│   │   ├── ConfigSyncDeserializer.php             # WP02 — YAML → entity (via factory)
│   │   ├── FieldValueMapper.php                   # WP02 — FieldDefinition type → YAML scalar/sequence/map
│   │   ├── ConfigManifestEntry.php                # WP02 — entry derived from sync file (meta + path + hash)
│   │   ├── ConfigExporter.php                     # WP03 — orchestrates active → sync writes
│   │   ├── ConfigImporter.php                     # WP04 — orchestrates sync → active writes in DAG order
│   │   ├── ConfigDiffer.php                       # WP05 — unified-diff renderer for sync vs active YAML
│   │   ├── ConfigStatusReporter.php               # WP05 — counts + per-entity table generator
│   │   ├── ConfigSyncValidator.php                # WP06 — runs FieldDefinition::validators() over sync YAML
│   │   └── ConfigResetter.php                     # WP07 — single-entity reset with confirmation prompt hook
│   ├── Audit/
│   │   ├── ConfigAuditChannel.php                 # WP07 — `config.audit` LoggerInterface channel constant
│   │   └── ConfigAuditEvent.php                   # WP07 — value object (operation, actor, entity, before/after summary, timestamp)
│   ├── Backend/
│   │   └── BackendRestrictionEnforcer.php         # WP08 — boot-time check; throws InvalidConfigBackendException
│   └── Exception/
│       ├── InvalidConfigBackendException.php      # WP08 — entity-type id + disallowed backend id + declaring FQCN
│       ├── ConfigSerializationException.php       # WP02 — filename↔_meta.entity_type mismatch and similar
│       ├── ConfigImportFailedException.php        # WP04 — per-entity import failure
│       └── ConfigCommandCollisionException.php    # WP09 — registered command collides with reserved verb
└── tests/
    ├── Contract/
    │   ├── ConfigDependencyInterfaceContractTest.php   # WP01 — no-op default
    │   ├── ConfigSyncRepositoryContractTest.php        # WP02 — read/write round-trip + naming rules
    │   └── ConfigImporterContractTest.php              # WP04 — DAG ordering + per-entity tx
    ├── Unit/
    │   ├── DependencyResolverTest.php                  # WP01 — cycle + missing fixtures
    │   ├── FieldValueMapperTest.php                    # WP02 — full type table coverage
    │   ├── ConfigDifferTest.php                        # WP05 — diff output snapshots
    │   └── BackendRestrictionEnforcerTest.php          # WP08 — boot-time exception cases
    └── Fixtures/
        ├── CycleFixture.php                            # WP10 — A → B → A
        └── MinooRoundTripFixture.php                   # WP10 — export-edit-import scenario

packages/cli/                                     # ADDITIONS at L6
└── src/Command/Config/
    ├── ConfigCommand.php                              # WP09 — base class + reserved-verb registry
    ├── ConfigExportCommand.php                        # WP03 — config:export
    ├── ConfigImportCommand.php                        # WP04 — config:import
    ├── ConfigDiffCommand.php                          # WP05 — config:diff
    ├── ConfigStatusCommand.php                        # WP05 — config:status
    ├── ConfigValidateCommand.php                      # WP06 — config:validate
    └── ConfigResetCommand.php                         # WP07 — config:reset

packages/entity-storage/                          # MINIMAL: boot-time backend hook
└── src/
    └── StorageBackendRegistry.php                     # WP08 — invokes BackendRestrictionEnforcer on registration

tests/Integration/PhaseN/                         # NEW phase number verified at task-outline
├── ConfigSyncRoundTripIntegrationTest.php             # WP10 — export → edit → import → diff = 0
├── ConfigImportDependencyOrderingTest.php             # WP10 — DAG-respect under real entity types
└── ConfigCommandCollisionBootTest.php                 # WP09 — kernel refuses to boot on collision

docs/
├── specs/
│   ├── config-management.md                            # WP11 — NEW canonical spec for shipped surface
│   ├── public-surface-map.md                           # WP11 — register new surface entries
│   └── stability-charter.md                            # WP11 — §5.5 amendment
├── cookbook/
│   └── config-sync.md                                  # WP11 — NEW operator guide
└── upgrade-notes/
    └── <alpha-train>.md                                # WP11 — NEW upgrade-guide entry per charter §7

CLAUDE.md                                        # WP11 — orchestration row for packages/config sync + audit
CHANGELOG.md                                     # WP11 — [Unreleased] Added bullet
```

## Phase 0: Research

See [`research.md`](research.md). Open questions resolved:

1. **R-01 / Drupal CMI patterns reviewed.** Sync-directory shape, manifest semantics, dependency-graph computation, and rename-via-UUID detection all map cleanly onto the existing `ConfigEntityBase`. Confirmed the field-value mapping table in spec §5.3 is sufficient for current entity types in Minoo.
2. **R-02 / Existing `ConfigEntityBase` surface analysis.** No breaking changes required. `ConfigDependencyInterface` is added as a new opt-in surface — the default no-op implementation on `ConfigEntityBase` keeps existing config entity classes compiling unchanged.
3. **R-03 / CLI namespace decisions.** Six reserved sub-verbs (`export`, `import`, `diff`, `status`, `validate`, `reset`) registered framework-side in `packages/cli`. Apps registering reserved verbs fail at boot via `ConfigCommandCollisionException`. App-defined `config:<custom>` verbs that do NOT collide are allowed.
4. **R-04 / Dependency-graph ordering for deterministic import.** Topological sort via DFS (`DependencyResolver`). Tie-break by lexicographic entity-id when multiple nodes have no remaining incoming edges — guarantees identical ordering across runs / processes, which makes failure-mode reproduction tractable.
5. **R-05 / Active/sync split interaction with multi-environment promotion.** CMI is environment-agnostic by design — same sync store applies to dev/staging/prod. Per-env values stay in env vars read by `config/waaseyaa.php`. Operators who hit "feature X enabled in staging only" are directed to the env-var pattern in the cookbook, NOT to per-env-sync stores. ADR 018 explicitly defers runtime config-store overrides to a future ADR.
6. **R-06 / Compatibility with future `sql-column` config entity opt-in.** Serialization is field-definition-driven, not backend-driven. A config entity migrated from `sql-blob` to `sql-column` (via entity-storage-v2's migration generator) continues to export/import identically — same `ConfigSyncFile` shape, same dependency declarations, same validator pipeline. The two missions are independent.

## Phase 1: Design

See:
- [`data-model.md`](data-model.md) — value object + service + storage shapes
- [`contracts/`](contracts/) — stable-surface contracts (four files)
- [`quickstart.md`](quickstart.md) — concrete export → edit → import walkthrough

Re-evaluating Charter Check after Phase 1 design: PASS unchanged.

## Risks and open questions

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Validation pipeline (`FieldDefinition::validators()` per ADR 013) not fully shipped at start of WP06 | Medium | Blocks WP06; cascades to WP10 | Confirm landing before WP01 starts (spec §10 open question 1). If unshipped, gate the mission on the validation-primitives mission landing first. |
| Operator preference for delete-default orphan handling diverges from spec's warn-default | Low | Cookbook friction; possible re-spec | Spec defaults to warn (safer); cookbook documents `--delete-orphans` opt-in prominently. Revisit at first operator feedback. |
| UUID generation strategy for legacy pre-CMI config entities | Medium | Initial export collides if two envs generate different UUIDs | Spec §10.3 recommends deterministic UUID = `sha-256(entity_type + entity_id)`. Re-confirm before WP02 implements `ConfigSyncSerializer`. |
| Atomicity boundary surprises operators | Medium | Partial-apply on failure misunderstood as bug | FR-023 + FR-028: per-entity transactions, continue on per-entity error. Cookbook documents the boundary explicitly with rollback recipe. |
| Cross-package config dependencies break under non-deterministic provider load order | Low | DAG resolution fails when producer package not yet registered | Confirm `PackageManifestCompiler::warm()` provider order is deterministic before WP04. If not, add an explicit `dependsOn` declaration on providers. |
| Sync-store YAML format drift between Symfony Yaml versions | Low | Spurious diffs after Symfony upgrade | Pin Symfony Yaml options (`Yaml::DUMP_OBJECT_AS_MAP`, sorted keys, block-style) in `ConfigSyncSerializer`. Snapshot test in WP02. |

## Phases / milestones

| Phase | WPs | Outcome |
|---|---|---|
| **Foundation** | WP01, WP02 | `ConfigDependencyInterface` shipped; DAG computation + cycle/missing detection landed; YAML sync-file format on stable surface; round-trip serializer/deserializer covered. |
| **CLI core** | WP03, WP04 | `config:export` + `config:import` operational. Import respects DAG, per-entity transactions, orphan-warn default. |
| **Inspection + validation** | WP05, WP06 | `config:diff` + `config:status` (read-only) plus `config:validate` (deploy-gate). |
| **Reset + audit** | WP07 | `config:reset` (single-entity) + `config.audit` log channel on stable surface. |
| **Hardening** | WP08, WP09 | Backend restriction enforced at boot; `config:*` namespace reserved with collision-check. |
| **Validation** | WP10 | Minoo round-trip test + cycle fixture test green in CI. |
| **Documentation** | WP11 | `docs/specs/config-management.md`, cookbook, upgrade-guide entry, charter §5.5 amendment all land. |

## Dependencies

**Cross-mission external dependencies:** none. CMI is independent of entity-storage-v2 (config entities can stay on `sql-blob` indefinitely) and of any sibling mission. The only internal dependency is on the shipped state of ADR 013's `FieldDefinition::validators()` pipeline, used by WP06 / `config:validate`; that pipeline is expected shipped at WP01 kick-off and is verified in the §10.1 open question.

**Charter dependencies:** charter §5.5 is amended (additive) as part of WP11. No prior charter sections need to land first.

**ADR dependencies:** [ADR 018](../../docs/adr/018-configuration-management-sync.md) (governing — accepted 2026-05-11); [ADR 010](../../docs/adr/010-multi-backend-field-storage.md) (backend restriction); [ADR 013](../../docs/adr/013-form-abstraction-apps-own.md) (validation pipeline reuse).

## Complexity tracking

| Item | Why it could be complex | Mitigation |
|---|---|---|
| Dependency-graph cycle detection messages | A 50-node graph with a deeply nested cycle is hard to read at the operator level; `A → B → C → ... → A` paths get long. | `ConfigDependencyCycleException` carries the full cycle as a list; renderer truncates with `…` after 5 hops for log/console output but preserves full path on `getCycle()` for tests. |
| YAML diff determinism across Symfony Yaml versions | Block-style vs flow-style switching, key ordering, multi-line string framing all vary between minor versions. | Pin emitter options; snapshot test of canonical fixture; CI failure on diff between two consecutive exports of unchanged data. |
| Orphan detection performance | Active store has N entities; sync store has M files. Naïve set-diff is O(N+M); per-entity-type partitioning keeps it manageable. | `ConfigStatusReporter` groups by entity-type, computes per-type set diff. Acceptable for any realistic config-entity count. |
| Boot-time backend-restriction check failing post-hoc | An app silently migrates a config entity to `vector` backend; kernel refuses to boot in prod after deploy. | `InvalidConfigBackendException` is thrown at boot per FR-045. The exception message includes the offending FQCN and disallowed backend id; runbook entry in the cookbook describes the recovery path (back out the backend change). |
| Reset confirmation prompt under non-TTY (CI) | `config:reset` prompts; CI invocations without `--yes` block indefinitely. | FR-042: prompt is suppressed when `--yes` is provided; otherwise the prompt detects non-TTY and refuses to proceed (exits non-zero) rather than hanging. |
| `_meta.uuid` rename detection across environments | Same logical entity, different ids in two envs: rename detection requires UUID stability. Legacy entities without UUIDs need deterministic UUID generation. | `ConfigSyncSerializer` generates a deterministic UUID = `sha-256(entity_type + entity_id)` (truncated to UUID shape) when none is present; confirmed in research §10.3. |

## Progress tracking

| Phase | Status | Date |
|---|---|---|
| Specify | DONE | 2026-05-15 |
| Plan (this file) | IN PROGRESS | 2026-05-16 |
| Tasks outline | pending | — |
| Tasks packages | pending | — |
| Tasks finalize | pending | — |
| Implement-review loop | pending | — |
| Merge | pending | — |

## References

- [`spec.md`](spec.md) — mission spec (61 FRs, 11 WPs)
- [ADR 018](../../docs/adr/018-configuration-management-sync.md) — governing decision
- [ADR 010](../../docs/adr/010-multi-backend-field-storage.md) — backend restriction
- [ADR 013](../../docs/adr/013-form-abstraction-apps-own.md) — validation pipeline reuse
- [`docs/specs/stability-charter.md`](../../docs/specs/stability-charter.md) §5.5 — amended at mission close
- [`docs/specs/drupal-comparison-matrix.md`](../../docs/specs/drupal-comparison-matrix.md) §1.5, §3.5 — gap origin

## Mandatory stop

This command (`/spec-kitty.plan`) is COMPLETE after generating the planning artifacts above. The next commands are `/spec-kitty.tasks-outline` → `/spec-kitty.tasks-packages` → `/spec-kitty.tasks-finalize` → implement-review loop dispatch.
