# Configuration Management (CMI) — Active/Sync Store Split

<!-- Spec reviewed 2026-05-16 - M-003 closure (config-management-v1-01KRCDEC) -->

**Status:** Shipped (M-003, mission `config-management-v1-01KRCDEC`, 2026-05-16)
**Audience:** framework maintainers + application authors operating multi-environment deployments.
**Governing ADR:** [ADR 018](../adr/018-configuration-management-sync.md) — Drupal-shape CMI with active/sync store split (Accepted 2026-05-11).
**Charter linkage:** [`stability-charter.md`](stability-charter.md) §5.5 enumerates the stable surface ratified by this mission; beta-gate criterion 9 (§3.2) is **SATISFIED** by this mission's landing.
**Mission archive:** [`kitty-specs/config-management-v1-01KRCDEC/`](../../kitty-specs/config-management-v1-01KRCDEC/) — original spec, plan, work packages, review history.

This is the canonical doctrine spec. The original mission spec
[`config-management-v1.md`](config-management-v1.md) is retained as a historical artifact;
this file is the single source of truth post-mission.

---

## 1. What ships

Eleven work packages composed an active/sync configuration substrate on top of
the pre-existing `ConfigEntityBase`. Existing config entities continue working
unchanged — CMI is purely additive.

**Subsystem surface (Layer 1, `packages/config/`):**

| Slice | Purpose | FQCNs |
|---|---|---|
| Dependency declarations | DAG ordering for import | `Waaseyaa\Config\Dependency\ConfigDependencyInterface` + `Dependency\Exception\ConfigDependencyCycleException`, `ConfigDependencyMissingException` |
| Sync-store format | Deterministic YAML serialization with `_meta` block | `Waaseyaa\Config\Sync\ConfigSyncFile`, `ConfigSyncSerializer`, `ConfigSyncDeserializer`, `ConfigSyncRepository`, `ConfigSyncFileSourceInterface`, `ConfigManifestEntry` |
| Orchestrators | One service per CLI command | `Waaseyaa\Config\Sync\ConfigExporter`, `ConfigImporter`, `ConfigDiffer`, `ConfigStatusReporter`, `ConfigSyncValidator`, `ConfigResetter`, `ConfigImportApplyHookInterface` |
| Audit channel | `config.audit` log channel | `Waaseyaa\Config\Audit\ConfigAuditChannel` (`CHANNEL` constant) + `ConfigAuditEvent` |
| Backend restriction | Boot-time guard: config entities limited to `sql-blob` / `sql-column` | `Waaseyaa\Config\Backend\BackendRestrictionEnforcer` + `Waaseyaa\Config\Exception\InvalidConfigBackendException` |
| CLI namespace reservation | Six reserved `config:*` sub-verbs | `Waaseyaa\CLI\Command\Config\ConfigCommand` (abstract base with `RESERVED_VERBS`, `RESERVED_FULL_VERBS`, `RESERVED_FQCNS` constants) + `Waaseyaa\Config\Exception\ConfigCommandCollisionException` |

**CLI surface (Layer 6, `packages/cli/`):** six commands under `bin/waaseyaa config:*`.

| Command | Class | Spec FRs |
|---|---|---|
| `config:export [--diff] [--dry-run]` | `Waaseyaa\CLI\Command\Config\ConfigExportCommand` | FR-017..FR-021 |
| `config:import [--dry-run] [--delete-orphans] [--halt-on-error] [--no-dependency-check]` | `Waaseyaa\CLI\Command\Config\ConfigImportCommand` | FR-022..FR-029 |
| `config:diff [<entity-type>.<id>]` | `Waaseyaa\CLI\Command\Config\ConfigDiffCommand` | FR-030..FR-033 |
| `config:status [--format=plain|json]` | `Waaseyaa\CLI\Command\Config\ConfigStatusCommand` | FR-034..FR-036 |
| `config:validate` | `Waaseyaa\CLI\Command\Config\ConfigValidateCommand` | FR-037..FR-040 |
| `config:reset <entity-type>.<id> [--yes]` | `Waaseyaa\CLI\Command\Config\ConfigResetCommand` | FR-041..FR-043 |

---

## 2. Architecture (one diagram)

```
┌──────────────────────────┐                          ┌──────────────────────────┐
│      Active store        │                          │       Sync store         │
│  (SQL — runtime config)  │                          │  (filesystem — YAML)     │
│  ConfigEntityBase rows   │                          │  storage/config-sync/   │
└──────────┬───────────────┘                          └─────────┬────────────────┘
           │                                                    │
           │       config:export ────────────────────────────►  │
           │  ◄─── config:import (DAG-ordered, per-entity tx)   │
           │  ◄─── config:reset <id>                            │
           │                                                    │
           ├─────────►  ConfigDiffer / ConfigStatusReporter ◄───┤
           │           (read-only inspection)                   │
           │                                                    │
           └─────────►  ConfigSyncValidator  ◄──────────────────┘
                       (FieldDefinition::validators())
```

**Boot-time gates:**
- `BackendRestrictionEnforcer` scans every config-entity type at boot; refuses non-`sql-blob` / non-`sql-column` declarations with `InvalidConfigBackendException`.
- `ConfigCommand::assertNoCollision()` runs during CLI registration; an app command claiming any reserved sub-verb (`export`, `import`, `diff`, `status`, `validate`, `reset`) fails with `ConfigCommandCollisionException`.

---

## 3. Sync-store file format (canonical)

### 3.1 Filename

`<entity_type>.<entity_id>.yml` — lowercase ASCII with `_` separators. Files outside this convention are ignored by `config:import` (warn-and-skip; not error).

Examples: `taxonomy_vocabulary.community_categories.yml`, `role.coordinator.yml`.

### 3.2 `_meta` block (leading)

```yaml
_meta:
  dependencies:
    - role.admin
    - taxonomy_vocabulary.parent_thing
  entity_type: taxonomy_vocabulary
  langcode: en
  uuid: 0193abc...
```

- `dependencies` — array of `<entity_type>.<entity_id>` strings consumed by the DAG.
- `entity_type` — must match the filename prefix; mismatch raises `ConfigSerializationException`.
- `langcode` — language code; default `en` for non-translatable config.
- `uuid` — stable across renames. When a sync file is renamed (entity id changes) but the uuid is preserved, the importer treats it as a rename, not a create+delete.

Keys within `_meta` are sorted alphabetically. New optional `_meta` keys may be added without deprecation; renames or removals follow charter §4.

### 3.3 Field values

The remaining top-level keys are entity field values, sorted alphabetically. The serializer maps `FieldDefinition` types to YAML representations:

| `FieldDefinition` type | YAML representation |
|---|---|
| `string` | scalar string |
| `int` | scalar int |
| `bool` | scalar bool |
| `datetime` | ISO 8601 string |
| `json` | mapping or sequence (native YAML structure) |
| `text` | scalar string (block scalar where appropriate) |
| `uuid` | scalar string |
| `entity_reference` | `<entity_type>.<entity_id>` string |
| `field_list` | sequence of scalars |

The table itself is stable; new field types extend additively. Removals / renames follow the deprecation cycle.

### 3.4 Determinism rules

- Alphabetical key ordering within `_meta` and within the top-level field group.
- Multi-line strings use YAML block scalars (`|` or `>`) when they contain newlines.
- Empty arrays/maps serialize as `[]` / `{}` (flow style) to reduce visual noise.
- The `_meta` block always appears first.

These rules are load-bearing — operator git diffs depend on them. They follow charter §4.

---

## 4. Dependency graph

`ConfigDependencyInterface::configDependencies(): array` returns `<entity_type>.<entity_id>` strings. `ConfigEntityBase` ships a default no-op implementation that returns `[]`; entity types override.

At import time `DependencyResolver` (internal):

1. Parses every file's `_meta.dependencies`.
2. Builds a directed graph: each file is a node; each dependency declaration is an edge from dependency → dependent.
3. Computes topological order; that becomes the import order.
4. Cycles raise `ConfigDependencyCycleException` carrying the full cycle path (DFS-based detection, hop-limited error message via `MESSAGE_HOP_LIMIT`).
5. Missing dependencies (entry references nonexistent config in both stores) raise `ConfigDependencyMissingException` carrying the missing id.

`--no-dependency-check` bypasses cycle and missing-dep detection for emergency recovery. Bypass is logged at `warning` level to `config.audit`.

Cross-package dependencies are supported transparently — the graph is global within the app's config-entity registry.

---

## 5. CLI command behaviours

(Normative reference; see [`docs/cookbook/config-sync.md`](../cookbook/config-sync.md) for operator walkthroughs.)

### 5.1 `config:export [--diff] [--dry-run]`

Walks the config-entity registry. For each entity, serialises to YAML per §3 and writes under `config.sync_path` (default `storage/config-sync/`). Output ends with `X created, Y updated, Z unchanged.`

- `--diff` writes only files whose content differs.
- `--dry-run` computes writes without filesystem effects.
- Exit code 0 on success; 1 on any serialisation error.

### 5.2 `config:import [--dry-run] [--delete-orphans] [--halt-on-error] [--no-dependency-check]`

1. Validates every sync file via `ConfigSyncValidator` (failures block unless `--no-dependency-check`).
2. Builds the DAG (§4).
3. Applies entities in topological order; each in its own DB transaction.
4. Per-entity diffs displayed when interactive (TTY); suppressed in CI.
5. Orphans (active-store entities with no sync file): default = warn-only; `--delete-orphans` opts into deletion.
6. Per-entity errors are counted and the run continues unless `--halt-on-error`.
7. Final exit code: 0 only if all entities succeeded.

### 5.3 `config:diff [<entity-type>.<id>]`

Unified diff of active vs sync YAML (serialised identically on both sides to avoid whitespace noise). UUID-tracked rename detection: a `_meta.uuid` match with a different id is rendered as a rename. Exit 0 if no differences, 1 otherwise.

### 5.4 `config:status [--format=plain|json]`

Counts: in-sync / drift / sync-only / active-only. Per-entity table when interactive and total < 50. Read-only (no side effects on either store).

### 5.5 `config:validate`

Parses every sync file. Instantiates the would-be entity without persisting. Runs `FieldDefinition::validators()` over each field. Per-entity errors with per-field detail. Exit 0 if all valid, 1 otherwise. Designed to run as a CI gate before `config:import`.

### 5.6 `config:reset <entity-type>.<id> [--yes]`

Loads the sync entity, overwrites the active entity (transactional, lifecycle events fire). Confirmation prompt unless `--yes`. Logs to `config.audit` with actor / before-after diff summary / timestamp.

---

## 6. Audit log channel

Channel constant: `Waaseyaa\Config\Audit\ConfigAuditChannel::CHANNEL` = `'config.audit'`. Event payload: `Waaseyaa\Config\Audit\ConfigAuditEvent`.

The channel receives:

- One event per `config:import` apply (per entity, after the per-entity transaction commits).
- One event per `config:export` write (per file created/updated).
- One event per `config:reset` apply.
- A `warning`-level event per `--no-dependency-check` bypass.
- A `warning`-level event per detected orphan when `config:import` runs without `--delete-orphans`.

The channel name is on stable surface (charter §4.4); operators wire `config.audit` into their log shipping with confidence.

---

## 7. Backend restriction

Config entities are restricted to `sql-blob` and `sql-column` backends (`Waaseyaa\Config\Backend\BackendRestrictionEnforcer::ALLOWED_BACKEND_IDS`). Attempts to declare `vector` or `remote` (or any future non-SQL backend) fail at boot with `InvalidConfigBackendException`, which carries:

- The offending entity-type id.
- The disallowed backend id.
- The FQCN of the declaring code.

Reason: config entities require deterministic, queryable serialization for CMI export/import to work. Vector or remote backends would either lose fidelity (vector quantisation) or fail to participate in transactional imports (remote).

---

## 8. CLI namespace reservation

`Waaseyaa\CLI\Command\Config\ConfigCommand` exposes three constants for boot-time collision detection:

- `RESERVED_VERBS` — the six short verbs (`export`, `import`, `diff`, `status`, `validate`, `reset`).
- `RESERVED_FULL_VERBS` — the qualified forms (`config:export`, etc.).
- `RESERVED_FQCNS` — the six concrete command FQCNs.

If an app or extension registers a command whose name matches any reserved sub-verb but whose class is NOT in `RESERVED_FQCNS`, registration fails with `ConfigCommandCollisionException`. The exception names the conflicting command class so operators can locate the offending package quickly.

Apps and extensions may freely register `config:<custom>` verbs that are NOT in the reserved set (e.g. `config:audit-export`, `config:snapshot`). They own those.

---

## 9. Per-environment override pattern (load-bearing)

CMI does **not** ship runtime config overrides (Drupal `$config['x']['y']` style). The supported pattern for per-environment values is **env vars consumed inside `config/waaseyaa.php`**.

Example:

```php
// config/waaseyaa.php
return [
    'feature_x' => [
        'enabled' => (bool) ($_ENV['FEATURE_X_ENABLED'] ?? false),
        'budget'  => (int)  ($_ENV['FEATURE_X_BUDGET']  ?? 100),
    ],
    // ...
];
```

`FEATURE_X_ENABLED=true` in staging's environment file, unset in production, no sync-store overrides involved. See [`docs/cookbook/config-sync.md`](../cookbook/config-sync.md) §6 for the full pattern.

Charter §11 names runtime overrides as a future-ADR door; if and when they ship, they will be a parallel mechanism, not a sync-store extension.

---

## 10. Stability tier map (matches charter §5.5)

| Symbol | Tier | Notes |
|---|---|---|
| `ConfigDependencyInterface` | stable | Charter §5.5; consumers safely implement. |
| `ConfigSyncFile`, `ConfigSyncSerializer`, `ConfigSyncDeserializer`, `ConfigSyncRepository` | stable | Format I/O. |
| `ConfigSyncFileSourceInterface`, `ConfigImportApplyHookInterface` | stable | Extension points. |
| `ConfigExporter`, `ConfigImporter`, `ConfigDiffer`, `ConfigStatusReporter`, `ConfigSyncValidator`, `ConfigResetter` | stable | Orchestrators. |
| `ConfigSyncManifestEntry` | stable | Manifest value object. |
| Sync-store YAML format (`_meta` shape, key sort order, filename convention) | stable | Load-bearing strings (charter §4 cycle for changes). |
| `config.sync_path` config key | stable | Default `storage/config-sync/`. |
| `ConfigAuditChannel`, `ConfigAuditEvent`, channel constant `config.audit` | stable | Charter §4.4 amendment. |
| `BackendRestrictionEnforcer`, `InvalidConfigBackendException` | stable | Boot-time gate. |
| `ConfigCommand` abstract base + 6 concrete `Config*Command` classes | stable | Six reserved sub-verbs. |
| `ConfigDependencyCycleException`, `ConfigDependencyMissingException`, `ConfigSerializationException`, `ConfigImportFailedException`, `ConfigCommandCollisionException` | stable | Error model. |
| `DependencyGraph`, `DependencyResolver` | internal | Topological-sort implementation; exceptions are the stable contract. |
| `FieldValueMapper` | internal | Per-field-type YAML emitter; the type→YAML table in §3.3 is the stable contract. |
| `DiffResult`, `StatusEntry`, `StatusReport`, `FieldViolation`, `ConfigExportFileResult`, `ConfigExportResult`, `ConfigImportEntryResult`, `ConfigImportResult`, `ConfigValidateEntry`, `ConfigValidateResult` | internal | Operator output is the contract; PHP shape is refactorable. |

---

## 11. Cross-references

- ADR 018 — governing decision (Accepted 2026-05-11).
- ADR 010 — multi-backend field storage (origin of the `sql-blob` / `sql-column` constraint).
- ADR 013 — form abstraction (origin of `FieldDefinition::validators()`).
- Charter [`stability-charter.md`](stability-charter.md) §5.5 (stable surface), §3.2 criterion 9 (CMI gap → SATISFIED), §4 (deprecation cycle), §4.4 (log channel registry), §11 (future-ADR doors including per-env overrides).
- Cookbook [`docs/cookbook/config-sync.md`](../cookbook/config-sync.md) — operator walkthrough.
- Conventions [`docs/conventions/cmi-sync-format.md`](../conventions/cmi-sync-format.md) — sync-store format invariants.
- Upgrade guide entry for the introducing alpha train — [`docs/upgrades/`](../upgrades/).
- Mission archive [`kitty-specs/config-management-v1-01KRCDEC/`](../../kitty-specs/config-management-v1-01KRCDEC/) — original spec, plan, work packages.
- Mission spec history [`config-management-v1.md`](config-management-v1.md) — pre-implementation working document (preserved for context).

---

## 12. Mission post-mortem

Mission `config-management-v1-01KRCDEC` (M-003, 2026-05-16) shipped FR-001..FR-061 across 11 work packages. Lane-a sequenced WP01 → WP02 → WP03/04/05/06 → WP07 → WP09 → WP10 → WP11; lane-b ran WP08 in parallel. Highlights:

- Zero breaking changes — existing `ConfigEntityBase` consumers untouched.
- Beta-gate criterion 9 (charter §3.2) — Drupal-comparison-matrix §3.5 (CMI) — flipped from `unshipped` to **SATISFIED**.
- Six reserved CLI sub-verbs landed with boot-fail collision detection (WP09), preventing future namespace squatting.
- Backend restriction landed independently on lane-b (WP08), unblocking the `sql-blob` / `sql-column` invariant claim that ADR 018 made on entity-storage-v2's behalf.
- Minoo round-trip (WP10) validates the substrate end-to-end: export → modify-in-sync → import → diff = 0.

Acceptance criteria §9 of [`config-management-v1.md`](config-management-v1.md) are satisfied; mission complete.
